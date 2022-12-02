-- migrate:up
CREATE TABLE relation_types (
    id uuid PRIMARY KEY,
    name text NOT NULL,
    UNIQUE (name)
);

CREATE TABLE albums_artists (
    album_id uuid NOT NULL REFERENCES albums(id),
    artist_id uuid NOT NULL REFERENCES artists(id),
    relation_type_id uuid NOT NULL REFERENCES relation_types(id),
    PRIMARY KEY (album_id, artist_id, relation_type_id)
);

CREATE TABLE tracks_artists (
    track_id uuid NOT NULL REFERENCES tracks(id),
    artist_id uuid NOT NULL REFERENCES artists(id),
    relation_type_id uuid NOT NULL REFERENCES relation_types(id),
    PRIMARY KEY (track_id, artist_id, relation_type_id)
);

ALTER TABLE albums
    DROP COLUMN artist_id,
    ALTER COLUMN discogs_id DROP NOT NULL,
    ALTER COLUMN disc_count DROP NOT NULL,
    ALTER COLUMN track_count DROP NOT NULL,
    ALTER COLUMN year DROP NOT NULL;
ALTER TABLE tracks
    DROP COLUMN artist_id,
    ALTER COLUMN disc_number DROP NOT NULL,
    ALTER COLUMN track_number DROP NOT NULL;

ALTER TABLE artists 
    ADD UNIQUE (name),
    ALTER COLUMN discogs_id DROP NOT NULL;

-- migrate:down

