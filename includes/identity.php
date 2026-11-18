<?php

const IDENTITY_TOKEN_PREFIX = 'LCID1:';

$GLOBALS['identity_storage_enabled'] = false;
$GLOBALS['identity_name_cache'] = [];

class IdentityPDO extends PDO
{
	public function __construct(string $dsn, ?string $username=null, ?string $password=null, ?array $options=null)
	{
		$options=$options ?? [];
		// PDO does not support custom statement classes on persistent connections.
		$options[PDO::ATTR_PERSISTENT]=false;
		parent::__construct($dsn, $username, $password, $options);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [IdentityPDOStatement::class, []]);
	}

	public function prepare(string $query, array $options=[]): PDOStatement|false
	{
		$caseInsensitiveParameters=[];
		if(identity_storage_is_enabled()){
			$query=identity_rewrite_case_insensitive_query($query, $caseInsensitiveParameters);
		}
		IdentityPDOStatement::set_next_query($query, $caseInsensitiveParameters);
		return parent::prepare($query, $options);
	}

}

class IdentityPDOStatement extends PDOStatement
{
	private static string $nextQuery='';
	private static array $nextCaseInsensitiveParameters=[];
	private string $query='';
	private array $caseInsensitiveParameters=[];

	protected function __construct()
	{
		$this->query=self::$nextQuery;
		$this->caseInsensitiveParameters=self::$nextCaseInsensitiveParameters;
	}

	public static function set_next_query(string $query, array $caseInsensitiveParameters): void
	{
		self::$nextQuery=$query;
		self::$nextCaseInsensitiveParameters=$caseInsensitiveParameters;
	}

	public function execute(?array $params=null): bool
	{
		if(identity_storage_is_enabled() && $params!==null){
			$params=identity_transform_parameters($this->query, $params, $this->caseInsensitiveParameters);
		}
		return parent::execute($params);
	}

	public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed
	{
		return $this->decode_row(parent::fetch($mode, $cursorOrientation, $cursorOffset));
	}

	public function fetchColumn(int $column=0): mixed
	{
		return identity_decode_result(parent::fetchColumn($column), $this->column_name($column));
	}

	private function decode_row(mixed $row): mixed
	{
		if(is_array($row)){
			foreach($row as $key=>$value){
				$column=is_string($key) ? $key : $this->column_name((int) $key);
				$row[$key]=identity_decode_result($value, $column);
			}
		}elseif(is_object($row)){
			foreach(get_object_vars($row) as $key=>$value){
				$row->$key=identity_decode_result($value, (string) $key);
			}
		}else{
			$row=identity_decode_result($row, $this->column_name(0));
		}
		return $row;
	}

	private function column_name(int $column): string
	{
		$metadata=$this->getColumnMeta($column);
		$name=is_array($metadata) ? (string) ($metadata['name'] ?? '') : '';
		if($name==='' && preg_match('/\bSELECT\s+pgpkey\b/i', $this->query)){
			return 'pgpkey';
		}
		return $name;
	}
}

function identity_storage_is_enabled(): bool
{
	return !empty($GLOBALS['identity_storage_enabled']);
}

function identity_storage_set_enabled(bool $enabled): void
{
	$GLOBALS['identity_storage_enabled']=$enabled;
	if(!$enabled){
		$GLOBALS['identity_name_cache']=[];
	}
}

function identity_table_name(): string
{
	return PREFIX . 'identity_index';
}

function identity_fields(): array
{
	return ['nickname', 'regedby', 'poster', 'recipient', 'ign', 'ignby', 'editedby', 'reporter', 'reviewed_by', 'created_by', 'pgpkey'];
}

function identity_lookup_fields(): array
{
	return ['nickname', 'regedby', 'poster', 'recipient', 'ign', 'ignby', 'editedby', 'reporter', 'reviewed_by', 'created_by'];
}

function identity_token_hash(string $value, bool $caseInsensitive=false): string
{
	define_encryption_constants();
	if($caseInsensitive){
		$value=mb_strtolower($value, 'UTF-8');
	}
	$domain=$caseInsensitive ? 'le-chat-php:identity:case-insensitive:' : 'le-chat-php:identity:exact:';
	return substr(hash_hmac('sha256', $domain . $value, ENCRYPTKEY_V2), 0, 32);
}

function identity_value_is_token(string $value): bool
{
	if(!preg_match('/^' . preg_quote(IDENTITY_TOKEN_PREFIX, '/') . '([a-f0-9]{32})$/', $value, $matches)){
		return false;
	}
	$db=$GLOBALS['db'] ?? null;
	if(!$db instanceof PDO){
		return false;
	}
	$stmt=$db->prepare('SELECT nickname_cipher FROM ' . identity_table_name() . ' WHERE identity_hash=?;');
	$stmt->execute([$matches[1]]);
	$cipher=$stmt->fetchColumn();
	return $cipher!==false && decrypt_database_value((string) $cipher)!==$value;
}

function identity_token_for(string $value, string $column, string $query=''): string
{
	if($value==='' || $column==='pgpkey'){
		return $value;
	}
	if($column==='recipient' && preg_match('/\b[a-zA-Z0-9_]*messages\b/i', $query) && preg_match('/^s [*?%_]$/', $value)){
		return $value;
	}
	if(identity_value_is_token($value)){
		return $value;
	}
	$db=$GLOBALS['db'] ?? null;
	if(!$db instanceof PDO){
		throw new RuntimeException('Identity storage requires an active database connection.');
	}
	$hash=identity_token_hash($value);
	$token=IDENTITY_TOKEN_PREFIX . $hash;
	if(isset($GLOBALS['identity_name_cache'][$hash])){
		return $token;
	}
	$lookup=$db->prepare('SELECT nickname_cipher FROM ' . identity_table_name() . ' WHERE identity_hash=?;');
	$lookup->execute([$hash]);
	$cipher=$lookup->fetchColumn();
	if($cipher===false){
		$cipher=encrypt_database_value($value);
		$insert=$db->prepare('INSERT INTO ' . identity_table_name() . ' (identity_hash, identity_fold, nickname_cipher) VALUES (?, ?, ?);');
		try {
			$insert->execute([$hash, identity_token_hash($value, true), $cipher]);
		} catch(PDOException $e){
			$lookup->execute([$hash]);
			$cipher=$lookup->fetchColumn();
			if($cipher===false){
				throw $e;
			}
		}
	}
	$storedValue=decrypt_database_value((string) $cipher);
	if(!hash_equals($storedValue, $value)){
		throw new RuntimeException('Identity token collision detected.');
	}
	$GLOBALS['identity_name_cache'][$hash]=$storedValue;
	return $token;
}

function identity_fold_token_for(string $value): string
{
	return identity_token_hash($value, true);
}

function identity_rewrite_case_insensitive_query(string $query, array &$parameterPositions): string
{
	$parameterPositions=[];
	$fields=implode('|', array_map(static function(string $field): string {
		return preg_quote($field, '/');
	}, identity_lookup_fields()));
	$column='((?:[A-Za-z_][A-Za-z0-9_]*\s*\.\s*)?`?(' . $fields . ')`?)';
	$pattern='/LOWER\s*\(\s*' . $column . '\s*\)\s*=\s*LOWER\s*\(\s*\?\s*\)/i';
	if(!preg_match_all($pattern, $query, $matches, PREG_OFFSET_CAPTURE)){
		return $query;
	}
	$rewritten='';
	$cursor=0;
	foreach($matches[0] as $index=>$match){
		$original=$match[0];
		$offset=$match[1];
		$questionPosition=$offset + strpos($original, '?');
		$parameterPositions[]=identity_sql_placeholder_index($query, $questionPosition);
		$rewritten.=substr($query, $cursor, $offset-$cursor);
		$rewritten.=$matches[1][$index][0] . ' IN (SELECT identity_hash FROM ' . identity_table_name() . ' WHERE identity_fold=?)';
		$cursor=$offset+strlen($original);
	}
	return $rewritten . substr($query, $cursor);
}

function identity_sql_placeholder_index(string $query, int $position): int
{
	$index=0;
	$quote='';
	$length=min($position, strlen($query));
	for($i=0; $i<$length; ++$i){
		$char=$query[$i];
		if($quote!==''){
			if($char==='\\' && $quote!=='`' && $i+1<$length){
				++$i;
			}elseif($char===$quote){
				if($i+1<$length && $query[$i+1]===$quote){
					++$i;
				}else{
					$quote='';
				}
			}
		}elseif($char==="'" || $char==='"' || $char==='`'){
			$quote=$char;
		}elseif($char==='?'){
			++$index;
		}
	}
	return $index;
}

function identity_sql_split_list(string $query): array
{
	$parts=[];
	$start=0;
	$depth=0;
	$quote='';
	$length=strlen($query);
	for($i=0; $i<$length; ++$i){
		$char=$query[$i];
		if($quote!==''){
			if($char==='\\' && $quote!=='`' && $i+1<$length){
				++$i;
			}elseif($char===$quote){
				if($i+1<$length && $query[$i+1]===$quote){
					++$i;
				}else{
					$quote='';
				}
			}
		}elseif($char==="'" || $char==='"' || $char==='`'){
			$quote=$char;
		}elseif($char==='('){
			++$depth;
		}elseif($char===')'){
			--$depth;
		}elseif($char===',' && $depth===0){
			$parts[]=substr($query, $start, $i-$start);
			$start=$i+1;
		}
	}
	$parts[]=substr($query, $start);
	return $parts;
}

function identity_sql_matching_parenthesis(string $query, int $openPosition): int
{
	$depth=0;
	$quote='';
	$length=strlen($query);
	for($i=$openPosition; $i<$length; ++$i){
		$char=$query[$i];
		if($quote!==''){
			if($char==='\\' && $quote!=='`' && $i+1<$length){
				++$i;
			}elseif($char===$quote){
				if($i+1<$length && $query[$i+1]===$quote){
					++$i;
				}else{
					$quote='';
				}
			}
		}elseif($char==="'" || $char==='"' || $char==='`'){
			$quote=$char;
		}elseif($char==='('){
			++$depth;
		}elseif($char===')' && --$depth===0){
			return $i;
		}
	}
	throw new RuntimeException('Could not parse an identity-bearing SQL statement.');
}

function identity_sql_parameter_fields(string $query): array
{
	$parameters=[];
	$fields=array_fill_keys(identity_fields(), true);
	if(preg_match('/\bINSERT(?:\s+OR\s+\w+)?\s+INTO\s+[A-Za-z0-9_."`]+\s*\(([^)]*)\)\s*VALUES\s*\(/i', $query, $insert, PREG_OFFSET_CAPTURE)){
		$columns=array_map(static function(string $column): string {
			return strtolower(trim($column, " \t\n\r\0\x0B`\""));
		}, explode(',', $insert[1][0]));
		$valuesOpen=$insert[0][1] + strlen($insert[0][0]) - 1;
		$valuesStart=$valuesOpen+1;
		$valuesEnd=identity_sql_matching_parenthesis($query, $valuesOpen);
		$expressions=identity_sql_split_list(substr($query, $valuesStart, $valuesEnd-$valuesStart));
		$position=$valuesStart;
		foreach($expressions as $index=>$expression){
			if(isset($columns[$index], $fields[$columns[$index]]) && ($question=strpos($expression, '?'))!==false){
				$parameters[identity_sql_placeholder_index($query, $position+$question)]=$columns[$index];
			}
			$position+=strlen($expression)+1;
		}
	}
	$fieldPattern=implode('|', array_map(static function(string $field): string {
		return preg_quote($field, '/');
	}, identity_fields()));
	$qualifier='(?:[A-Za-z_][A-Za-z0-9_]*\s*\.\s*)?';
	$comparisonPattern='/\b' . $qualifier . '`?(' . $fieldPattern . ')`?\s*(?:=|<>|!=)\s*\?/i';
	if(preg_match_all($comparisonPattern, $query, $matches, PREG_OFFSET_CAPTURE)){
		foreach($matches[0] as $index=>$match){
			$questionPosition=$match[1] + strpos($match[0], '?');
			$parameters[identity_sql_placeholder_index($query, $questionPosition)]=strtolower($matches[1][$index][0]);
		}
	}
	$inPattern='/\b' . $qualifier . '`?(' . $fieldPattern . ')`?\s+(?:NOT\s+)?IN\s*\(([^()]*)\)/i';
	if(preg_match_all($inPattern, $query, $matches, PREG_OFFSET_CAPTURE)){
		foreach($matches[0] as $index=>$match){
			$field=strtolower($matches[1][$index][0]);
			$list=$matches[2][$index][0];
			$listOffset=$matches[2][$index][1];
			if(preg_match_all('/\?/', $list, $questions, PREG_OFFSET_CAPTURE)){
				foreach($questions[0] as $question){
					$parameters[identity_sql_placeholder_index($query, $listOffset+$question[1])]=$field;
				}
			}
		}
	}
	return $parameters;
}

function identity_transform_parameters(string $query, array $parameters, array $caseInsensitiveParameters): array
{
	$fieldByPosition=identity_sql_parameter_fields($query);
	foreach($parameters as $position=>$value){
		if(!is_int($position) || !is_string($value)){
			continue;
		}
		if(in_array($position, $caseInsensitiveParameters, true)){
			$parameters[$position]=identity_fold_token_for($value);
			continue;
		}
		if(!isset($fieldByPosition[$position])){
			continue;
		}
		$field=$fieldByPosition[$position];
		if($field==='pgpkey'){
			if($value!=='' && strpos($value, 'LCENC2:')!==0){
				$parameters[$position]=encrypt_database_value($value);
			}
		}else{
			$parameters[$position]=identity_token_for($value, $field, $query);
		}
	}
	return $parameters;
}

function identity_decode_result(mixed $value, string $column=''): mixed
{
	if(!identity_storage_is_enabled()){
		return $value;
	}
	if(is_string($value)){
		if($column==='pgpkey' && strpos($value, 'LCENC2:')===0){
			return decrypt_database_value($value);
		}
		if(!preg_match('/^' . preg_quote(IDENTITY_TOKEN_PREFIX, '/') . '([a-f0-9]{32})$/', $value, $matches)){
			return $value;
		}
		$hash=$matches[1];
		if(isset($GLOBALS['identity_name_cache'][$hash])){
			return $GLOBALS['identity_name_cache'][$hash];
		}
		$db=$GLOBALS['db'] ?? null;
		if(!$db instanceof PDO){
			throw new RuntimeException('Identity decoding requires an active database connection.');
		}
		$stmt=$db->prepare('SELECT nickname_cipher FROM ' . identity_table_name() . ' WHERE identity_hash=?;');
		$stmt->execute([$hash]);
		$cipher=$stmt->fetchColumn();
		if($cipher===false){
			throw new RuntimeException('An encrypted identity lookup is missing.');
		}
		$name=decrypt_database_value((string) $cipher);
		$GLOBALS['identity_name_cache'][$hash]=$name;
		return $name;
	}
	if(is_array($value)){
		foreach($value as $key=>$entry){
			$value[$key]=identity_decode_result($entry, is_string($key) ? $key : '');
		}
		return $value;
	}
	if(is_object($value)){
		foreach(get_object_vars($value) as $key=>$entry){
			$value->$key=identity_decode_result($entry, (string) $key);
		}
	}
	return $value;
}

function identity_sorted_rows(PDOStatement $statement, string $field, ?string $secondaryField=null): array
{
	$rows=identity_decode_result($statement->fetchAll(PDO::FETCH_ASSOC));
	usort($rows, static function(array $left, array $right) use ($field, $secondaryField): int {
		$comparison=strcmp(mb_strtolower((string) $left[$field], 'UTF-8'), mb_strtolower((string) $right[$field], 'UTF-8'));
		if($comparison===0 && $secondaryField!==null){
			$comparison=strcmp(mb_strtolower((string) $left[$secondaryField], 'UTF-8'), mb_strtolower((string) $right[$secondaryField], 'UTF-8'));
		}
		return $comparison;
	});
	return $rows;
}

function identity_create_schema(): void
{
	global $db;
	if(DBDRIVER===0){
		$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
		$diskengine=' ENGINE=InnoDB';
	}else{
		$charset='';
		$diskengine='';
	}
	$db->exec('CREATE TABLE IF NOT EXISTS ' . identity_table_name() . " (identity_hash varchar(32) NOT NULL PRIMARY KEY, identity_fold varchar(32) NOT NULL, nickname_cipher text NOT NULL)$diskengine$charset;");
}

function identity_migrate_column(string $table, string $column): void
{
	global $db;
	$select=$db->prepare('SELECT id, ' . $column . ' FROM ' . PREFIX . $table . ' WHERE id>? ORDER BY id LIMIT 500;');
	$update=$db->prepare('UPDATE ' . PREFIX . $table . ' SET ' . $column . '=? WHERE id=?;');
	$lastId=0;
	do {
		$select->execute([$lastId]);
		$rows=$select->fetchAll(PDO::FETCH_ASSOC);
		foreach($rows as $row){
			$lastId=(int) $row['id'];
			$value=(string) ($row[$column] ?? '');
			if($column==='pgpkey'){
				if($value!=='' && strpos($value, 'LCENC2:')!==0){
					$update->execute([encrypt_database_value($value), $row['id']]);
				}
				continue;
			}
			$token=identity_token_for($value, $column, PREFIX . $table);
			if($token!==$value){
				$update->execute([$token, $row['id']]);
			}
		}
	} while(count($rows)===500);
}

function migrate_identity_storage(): bool
{
	global $db;
	$startedTransaction=false;
	try {
		if(!$db->inTransaction()){
			$db->beginTransaction();
			$startedTransaction=true;
		}
		$columns=[
			['members', 'nickname'],
			['members', 'regedby'],
			['messages', 'poster'],
			['messages', 'recipient'],
			['inbox', 'poster'],
			['inbox', 'recipient'],
			['ignored', 'ign'],
			['ignored', 'ignby'],
			['member_roles', 'nickname'],
			['likes', 'nickname'],
			['notes', 'editedby'],
			['reports', 'reporter'],
			['reports', 'poster'],
			['reports', 'reviewed_by'],
			['bots', 'created_by'],
			['members', 'pgpkey'],
		];
		foreach($columns as [$table, $column]){
			identity_migrate_column($table, $column);
		}
		if($startedTransaction){
			$db->commit();
		}
		identity_migrate_column('sessions', 'nickname');
		identity_storage_set_enabled(true);
		return true;
	} catch(Throwable $e){
		if($startedTransaction && $db->inTransaction()){
			$db->rollBack();
		}
		identity_storage_set_enabled(false);
		error_log('Identity data migration failed: ' . $e->getMessage());
		send_fatal_error(_('Identity data migration failed. Check the server log before retrying.'));
		return false;
	}
}
