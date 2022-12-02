-- migrate:up
ALTER TABLE tracks ADD UNIQUE (path);

-- migrate:down

