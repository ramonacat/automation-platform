-- migrate:up
CREATE TABLE "files" (
    id UUID,
    mount_id TEXT,
    path TEXT,
    modified_date TIMESTAMP WITH TIME ZONE
)

-- migrate:down

