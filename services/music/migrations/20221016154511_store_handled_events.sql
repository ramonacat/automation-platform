-- migrate:up
CREATE TABLE handled_events (
    id uuid PRIMARY KEY,
    timestamp timestamp with time zone NOT NULL
);

-- migrate:down

