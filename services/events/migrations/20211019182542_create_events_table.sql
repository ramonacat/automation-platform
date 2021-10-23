-- migrate:up

CREATE TABLE events (
    id UUID PRIMARY KEY,
    created_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    type TEXT NOT NULL,
    data JSONB NOT NULL
)

-- migrate:down

