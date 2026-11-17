<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="referrer" content="no-referrer">
	<title>LE CHAT bot API</title>
</head>
<body>
	<main>
		<h1>LE CHAT bot API</h1>
		<p>Bots use JSON requests and must authenticate every API request with an authorization token. Create and manage bots at <code>/bots.php</code>; the token is shown only when the bot is created or its token is rotated.</p>
		<p>Send requests over HTTPS. Put the token in the <code>Authorization</code> header, never in a URL or request body:</p>
		<pre><code>Authorization: Bearer YOUR_BOT_TOKEN</code></pre>

		<h2>Check authentication</h2>
		<p><code>GET /api/auth.php</code> validates the token and returns the bot identity.</p>
		<pre><code>curl -H "Authorization: Bearer YOUR_BOT_TOKEN" \
  https://chat.example/api/auth.php</code></pre>
		<pre><code>{"authenticated":true,"bot":{"id":12,"name":"weather"}}</code></pre>

		<h2>Read room messages</h2>
		<p><code>GET /api/room-read.php</code> returns messages visible to a guest in a room. Bots can access only rooms available to guests. Optional query parameters are:</p>
		<ul>
			<li><code>room_id</code>: room identifier; defaults to <code>1</code>.</li>
			<li><code>since_id</code>: return messages with a greater message ID; defaults to <code>0</code>.</li>
			<li><code>limit</code>: number of messages from <code>1</code> to <code>100</code>; defaults to <code>50</code>.</li>
		</ul>
		<p>Each message includes <code>id</code>, <code>created_at</code> (Unix timestamp), <code>poster</code>, <code>text</code> (the chat's formatted HTML), and <code>reply_to</code>. Use the returned <code>next_since_id</code> as the next request's <code>since_id</code>.</p>
		<pre><code>{"room":{"id":1,"name":"General"},"messages":[{"id":42,"created_at":1710000000,"poster":"Alice","text":"Hello","reply_to":0}],"next_since_id":42}</code></pre>
		<pre><code>curl -H "Authorization: Bearer YOUR_BOT_TOKEN" \
  "https://chat.example/api/room-read.php?room_id=1&amp;since_id=0"</code></pre>
		<p>Room reads are limited to one request every 10 seconds per bot across all rooms. A limited response has status <code>429</code> and a <code>Retry-After</code> header.</p>

		<h2>Send a message</h2>
		<p><code>POST /api/send.php</code> accepts a JSON object with a message and optional room identifier. Messages are posted publicly as the bot.</p>
		<pre><code>curl -X POST \
  -H "Authorization: Bearer YOUR_BOT_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"room_id":1,"message":"Hello from my bot"}' \
  https://chat.example/api/send.php</code></pre>
		<pre><code>{"sent":true,"room_id":1}</code></pre>
		<p>Each bot may send at most 5 messages in any rolling 10-second period. A limited response has status <code>429</code> and a <code>Retry-After</code> header.</p>

		<h2>Responses and errors</h2>
		<p>Responses use JSON. Successful sends return <code>201</code>; invalid or revoked tokens return <code>401</code>; inaccessible rooms return <code>403</code>; invalid request data returns <code>400</code>, <code>415</code>, or <code>422</code>; request bodies over 1 MiB return <code>413</code>; rate limits return <code>429</code>.</p>
		<pre><code>{"error":"Invalid or revoked bot token."}</code></pre>
		<p>Bot names and token values are encrypted in the bot registry. A token cannot be recovered after it is shown; rotate it if lost or exposed.</p>
	</main>
</body>
</html>