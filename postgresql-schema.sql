--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE categories (
    category_id integer NOT NULL,
    category_name text NOT NULL,
    category_group text
);


ALTER TABLE public.categories OWNER TO podnounce;

--
-- Name: categories_category_id_seq; Type: SEQUENCE; Schema: public; Owner: podnounce
--

CREATE SEQUENCE categories_category_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.categories_category_id_seq OWNER TO podnounce;

--
-- Name: categories_category_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: podnounce
--

ALTER SEQUENCE categories_category_id_seq OWNED BY categories.category_id;


--
-- Name: episodes; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE episodes (
    episode_id integer NOT NULL,
    show_id integer NOT NULL,
    season_number smallint NOT NULL,
    episode_number smallint NOT NULL,
    title text NOT NULL,
    summary text NOT NULL,
    explicit boolean NOT NULL,
    guid uuid NOT NULL,
    media_id integer,
    show_notes text,
    created_ts timestamp with time zone,
    created_by integer,
    publish_ts date DEFAULT now() NOT NULL
);


ALTER TABLE public.episodes OWNER TO podnounce;

--
-- Name: COLUMN episodes.show_notes; Type: COMMENT; Schema: public; Owner: podnounce
--

COMMENT ON COLUMN episodes.show_notes IS 'markdown supported';


--
-- Name: episodes_episode_id_seq; Type: SEQUENCE; Schema: public; Owner: podnounce
--

CREATE SEQUENCE episodes_episode_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.episodes_episode_id_seq OWNER TO podnounce;

--
-- Name: episodes_episode_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: podnounce
--

ALTER SEQUENCE episodes_episode_id_seq OWNED BY episodes.episode_id;


--
-- Name: media; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE media (
    media_id integer NOT NULL,
    fname_nice text NOT NULL,
    fname_on_disk text NOT NULL,
    media_bytes bigint NOT NULL,
    mime_type text NOT NULL,
    download_count bigint,
    duration interval
);


ALTER TABLE public.media OWNER TO podnounce;

--
-- Name: shows; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE shows (
    show_id integer NOT NULL,
    title text NOT NULL,
    short_description text,
    full_description text,
    author text,
    explicit boolean DEFAULT false NOT NULL,
    active boolean DEFAULT true NOT NULL,
    cover_art_id integer,
    license_id text,
    category_id integer,
    title_template text,
    summary_template text,
    notes_template text
);


ALTER TABLE public.shows OWNER TO podnounce;

--
-- Name: COLUMN shows.cover_art_id; Type: COMMENT; Schema: public; Owner: podnounce
--

COMMENT ON COLUMN shows.cover_art_id IS 'FKey to ''media'' table';


--
-- Name: COLUMN shows.license_id; Type: COMMENT; Schema: public; Owner: podnounce
--

COMMENT ON COLUMN shows.license_id IS 'fkey to licenses table';


--
-- Name: firehose_feed; Type: VIEW; Schema: public; Owner: podnounce
--

CREATE VIEW firehose_feed AS
    SELECT s.show_id, s.title AS show_title, c.category_id, c.category_name, c.category_group, s.short_description AS show_short_description, s.full_description AS show_full_description, s.author AS show_author, s.explicit AS show_explicit, s.cover_art_id, e.episode_id, e.season_number, e.episode_number, e.title, e.summary, e.publish_ts, e.show_notes, e.explicit AS episode_explicit, e.guid, m.media_id, m.fname_nice, m.fname_on_disk, m.media_bytes, m.mime_type, m.duration FROM (((episodes e JOIN shows s USING (show_id)) LEFT JOIN media m USING (media_id)) LEFT JOIN categories c USING (category_id)) WHERE (s.active IS TRUE);


ALTER TABLE public.firehose_feed OWNER TO podnounce;

--
-- Name: licenses; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE licenses (
    license_id text NOT NULL,
    description text NOT NULL
);


ALTER TABLE public.licenses OWNER TO podnounce;

--
-- Name: media_media_id_seq; Type: SEQUENCE; Schema: public; Owner: podnounce
--

CREATE SEQUENCE media_media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.media_media_id_seq OWNER TO podnounce;

--
-- Name: media_media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: podnounce
--

ALTER SEQUENCE media_media_id_seq OWNED BY media.media_id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE settings (
    setting text NOT NULL,
    value text
);


ALTER TABLE public.settings OWNER TO podnounce;

--
-- Name: show_feed; Type: VIEW; Schema: public; Owner: podnounce
--

CREATE VIEW show_feed AS
    SELECT e.episode_id, e.show_id, e.season_number, e.episode_number, e.title, e.summary, e.publish_ts, m.duration, e.explicit AS episode_explicit, e.guid, m.media_id, m.fname_nice, m.fname_on_disk, m.media_bytes, m.mime_type, e.show_notes FROM (episodes e JOIN media m USING (media_id));


ALTER TABLE public.show_feed OWNER TO podnounce;

--
-- Name: shows_show_id_seq; Type: SEQUENCE; Schema: public; Owner: podnounce
--

CREATE SEQUENCE shows_show_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.shows_show_id_seq OWNER TO podnounce;

--
-- Name: shows_show_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: podnounce
--

ALTER SEQUENCE shows_show_id_seq OWNED BY shows.show_id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE TABLE users (
    username text NOT NULL,
    passwd text,
    user_id integer NOT NULL,
    last_login_ts timestamp with time zone,
    last_login_ip inet
);


ALTER TABLE public.users OWNER TO podnounce;

--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: podnounce
--

CREATE SEQUENCE users_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.users_user_id_seq OWNER TO podnounce;

--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: podnounce
--

ALTER SEQUENCE users_user_id_seq OWNED BY users.user_id;


--
-- Name: category_id; Type: DEFAULT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY categories ALTER COLUMN category_id SET DEFAULT nextval('categories_category_id_seq'::regclass);


--
-- Name: episode_id; Type: DEFAULT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY episodes ALTER COLUMN episode_id SET DEFAULT nextval('episodes_episode_id_seq'::regclass);


--
-- Name: media_id; Type: DEFAULT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY media ALTER COLUMN media_id SET DEFAULT nextval('media_media_id_seq'::regclass);


--
-- Name: show_id; Type: DEFAULT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY shows ALTER COLUMN show_id SET DEFAULT nextval('shows_show_id_seq'::regclass);


--
-- Name: user_id; Type: DEFAULT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY users ALTER COLUMN user_id SET DEFAULT nextval('users_user_id_seq'::regclass);


--
-- Name: categories_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (category_id);


--
-- Name: episodes_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_pkey PRIMARY KEY (episode_id);


--
-- Name: episodes_season_episode_uniq; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_season_episode_uniq UNIQUE (show_id, season_number, episode_number);


--
-- Name: licenses_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY licenses
    ADD CONSTRAINT licenses_pkey PRIMARY KEY (license_id);


--
-- Name: media_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY media
    ADD CONSTRAINT media_pkey PRIMARY KEY (media_id);


--
-- Name: settings_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (setting);


--
-- Name: shows_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY shows
    ADD CONSTRAINT shows_pkey PRIMARY KEY (show_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: podnounce; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (username);


--
-- Name: fki_category_id_fkey; Type: INDEX; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE INDEX fki_category_id_fkey ON shows USING btree (category_id);


--
-- Name: fki_covert_art_id_fkey; Type: INDEX; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE INDEX fki_covert_art_id_fkey ON shows USING btree (cover_art_id);


--
-- Name: fki_license_id_fkey; Type: INDEX; Schema: public; Owner: podnounce; Tablespace: 
--

CREATE INDEX fki_license_id_fkey ON shows USING btree (license_id);


--
-- Name: category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY shows
    ADD CONSTRAINT category_id_fkey FOREIGN KEY (category_id) REFERENCES categories(category_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: covert_art_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY shows
    ADD CONSTRAINT covert_art_id_fkey FOREIGN KEY (cover_art_id) REFERENCES media(media_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: license_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY shows
    ADD CONSTRAINT license_id_fkey FOREIGN KEY (license_id) REFERENCES licenses(license_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: media_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT media_id_fkey FOREIGN KEY (media_id) REFERENCES media(media_id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: show_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: podnounce
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT show_id_fkey FOREIGN KEY (show_id) REFERENCES shows(show_id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

