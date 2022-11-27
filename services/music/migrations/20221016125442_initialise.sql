-- migrate:up

CREATE TABLE artists (
    id uuid PRIMARY KEY,
    discogs_id text NOT NULL,
    name text NOT NULL
);

CREATE TABLE albums (
    id UUID PRIMARY KEY,
    discogs_id text NOT NULL,
    title TEXT NOT NULL,
    artist_id UUID NOT NULL REFERENCES artists(id),
    disc_count INTEGER NOT NULL,
    track_count INTEGER NOT NULL,
    year INTEGER NOT NULL
);

CREATE TABLE tracks (
    id UUID PRIMARY KEY,
    title TEXT NOT NULL,
    album_id UUID NOT NULL REFERENCES albums(id),
    artist_id UUID NOT NULL REFERENCES artists(id),
    disc_number INTEGER NOT NULL,
    track_number INTEGER NOT NULL,
    path JSONB NOT NULL -- NOTE this is a serialized object with the mount id and path
);

-- migrate:down

