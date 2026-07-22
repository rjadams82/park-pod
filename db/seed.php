
<?php

$db = new PDO('sqlite:' . __DIR__ . '/db.sqlite');

$db->exec("
INSERT INTO providers (name, type, endpoint, ttl, enabled)
VALUES
('Wikipedia Summary', 'wikipedia', 'https://en.wikipedia.org/api/rest_v1/page/summary/', 86400, 1),
('Reddit Topic Feed', 'reddit', 'https://www.reddit.com/search.json?q=', 1800, 1)
");
