CREATE TABLE IF NOT EXISTS providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,          -- rss, wikipedia, reddit, youtube, custom
    endpoint TEXT NOT NULL,      -- feed URL or API URL
    api_key TEXT,                -- optional
    ttl INTEGER DEFAULT 3600,    -- cache time in seconds
    enabled INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS content_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER NOT NULL,
    topic TEXT NOT NULL,
    data TEXT NOT NULL,          -- JSON blob
    fetched_at INTEGER NOT NULL,
    FOREIGN KEY(provider_id) REFERENCES providers(id)
);

CREATE TABLE IF NOT EXISTS provider_fetch_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id INTEGER,
    provider_name TEXT NOT NULL,
    provider_type TEXT NOT NULL,
    host TEXT,
    topic TEXT NOT NULL,
    endpoint TEXT,
    status TEXT NOT NULL,
    message TEXT NOT NULL,
    item_count INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT,
    name TEXT,
    email TEXT,
    message TEXT,
    archived INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'admin'
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS parked_domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    host TEXT UNIQUE NOT NULL,
    category TEXT NOT NULL,
    subject_tags TEXT NOT NULL,
    enabled INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    host TEXT NOT NULL,
    domain TEXT NOT NULL,
    path TEXT NOT NULL DEFAULT '/',
    referrer TEXT,
    user_ip TEXT,
    meta TEXT,
    created_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_access_logs_host ON access_logs(host);
CREATE INDEX IF NOT EXISTS idx_access_logs_domain ON access_logs(domain);
CREATE INDEX IF NOT EXISTS idx_access_logs_created_at ON access_logs(created_at);
