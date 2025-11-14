--
-- PostgreSQL database dump
--

\restrict bs6gXXtmg4kDhigoaXqoVhnuqcf6dtfawgVVjsAt959jStryiVtZqpOpoV6A09m

-- Dumped from database version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)
-- Dumped by pg_dump version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: notify_messenger_messages(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.notify_messenger_messages() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
        RETURN NEW;
    END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: article_author; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.article_author (
    article_id integer NOT NULL,
    author_id integer NOT NULL
);


--
-- Name: article_image; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.article_image (
    id integer NOT NULL,
    article_id integer NOT NULL,
    image_id integer NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    is_featured boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN article_image.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.article_image.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: article_image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.article_image_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: article_image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.article_image_id_seq OWNED BY public.article_image.id;


--
-- Name: article_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.article_locks (
    id integer NOT NULL,
    article_id integer NOT NULL,
    locked_by_id integer NOT NULL,
    locked_at timestamp(0) without time zone NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    session_id character varying(255) DEFAULT NULL::character varying
);


--
-- Name: COLUMN article_locks.locked_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.article_locks.locked_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN article_locks.expires_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.article_locks.expires_at IS '(DC2Type:datetime_immutable)';


--
-- Name: article_locks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.article_locks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: article_locks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.article_locks_id_seq OWNED BY public.article_locks.id;


--
-- Name: article_stats_daily; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.article_stats_daily (
    id integer NOT NULL,
    article_id integer NOT NULL,
    date date NOT NULL,
    views integer DEFAULT 0 NOT NULL,
    unique_visitors integer DEFAULT 0 NOT NULL,
    avg_reading_time integer,
    completion_rate numeric(5,2)
);


--
-- Name: article_stats_daily_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.article_stats_daily_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: article_stats_daily_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.article_stats_daily_id_seq OWNED BY public.article_stats_daily.id;


--
-- Name: articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.articles (
    id integer NOT NULL,
    category_id integer,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    lead text,
    content text,
    status character varying(20) NOT NULL,
    badge character varying(20) DEFAULT NULL::character varying,
    is_featured boolean DEFAULT false NOT NULL,
    view_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL,
    published_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    publish_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);


--
-- Name: COLUMN articles.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.articles.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN articles.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.articles.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN articles.published_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.articles.published_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN articles.publish_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.articles.publish_at IS '(DC2Type:datetime_immutable)';


--
-- Name: articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.articles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.articles_id_seq OWNED BY public.articles.id;


--
-- Name: authors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.authors (
    id integer NOT NULL,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    email character varying(180) NOT NULL,
    slug character varying(255) NOT NULL,
    bio text,
    status character varying(20) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    twitter character varying(100) DEFAULT NULL::character varying,
    facebook character varying(255) DEFAULT NULL::character varying,
    linkedin character varying(255) DEFAULT NULL::character varying,
    website character varying(255) DEFAULT NULL::character varying,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN authors.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.authors.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN authors.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.authors.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: authors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.authors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: authors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.authors_id_seq OWNED BY public.authors.id;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    status character varying(20) NOT NULL,
    on_front_page boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN categories.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.categories.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN categories.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.categories.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: doctrine_migration_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.doctrine_migration_versions (
    version character varying(191) NOT NULL,
    executed_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    execution_time integer
);


--
-- Name: ext_translations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ext_translations (
    id integer NOT NULL,
    locale character varying(8) NOT NULL,
    object_class character varying(191) NOT NULL,
    field character varying(32) NOT NULL,
    foreign_key character varying(64) NOT NULL,
    content text
);


--
-- Name: ext_translations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ext_translations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ext_translations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ext_translations_id_seq OWNED BY public.ext_translations.id;


--
-- Name: external_article_mappings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.external_article_mappings (
    id integer NOT NULL,
    article_id integer NOT NULL,
    source character varying(50) NOT NULL,
    external_id character varying(255) NOT NULL,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN external_article_mappings.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.external_article_mappings.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN external_article_mappings.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.external_article_mappings.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: external_article_mappings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.external_article_mappings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: external_article_mappings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.external_article_mappings_id_seq OWNED BY public.external_article_mappings.id;


--
-- Name: images; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.images (
    id integer NOT NULL,
    filename character varying(255) NOT NULL,
    original_filename character varying(255) NOT NULL,
    path character varying(500) DEFAULT NULL::character varying,
    mime_type character varying(100) DEFAULT NULL::character varying,
    size integer,
    width integer NOT NULL,
    height integer NOT NULL,
    alt character varying(255) DEFAULT NULL::character varying,
    caption text,
    description text,
    image_author character varying(255) DEFAULT NULL::character varying,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN images.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.images.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN images.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.images.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: images_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.images_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: images_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.images_id_seq OWNED BY public.images.id;


--
-- Name: important_articles_list; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.important_articles_list (
    id integer NOT NULL,
    article_id integer NOT NULL,
    "position" integer NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN important_articles_list.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.important_articles_list.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: important_articles_list_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.important_articles_list_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: important_articles_list_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.important_articles_list_id_seq OWNED BY public.important_articles_list.id;


--
-- Name: live_text_ab_test_live_texts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_ab_test_live_texts (
    live_text_ab_test_id integer NOT NULL,
    live_text_id integer NOT NULL
);


--
-- Name: live_text_ab_tests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_ab_tests (
    id integer NOT NULL,
    created_by_id integer NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    hypothesis text,
    status character varying(20) NOT NULL,
    variant_type character varying(50) NOT NULL,
    control_variant json NOT NULL,
    test_variants json NOT NULL,
    traffic_allocation integer NOT NULL,
    target_metric character varying(100) NOT NULL,
    min_sample_size integer,
    significance_level numeric(3,2) DEFAULT NULL::numeric,
    start_date timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    end_date timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    results json,
    winner_variant character varying(100) DEFAULT NULL::character varying,
    confidence_level numeric(5,2) DEFAULT NULL::numeric,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_ab_tests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_ab_tests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_ab_tests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_ab_tests_id_seq OWNED BY public.live_text_ab_tests.id;


--
-- Name: live_text_collaborators; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_collaborators (
    id integer NOT NULL,
    live_text_id integer NOT NULL,
    user_id integer NOT NULL,
    role character varying(50) DEFAULT 'contributor'::character varying NOT NULL,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_collaborators_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_collaborators_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_collaborators_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_collaborators_id_seq OWNED BY public.live_text_collaborators.id;


--
-- Name: live_text_match_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_match_events (
    id integer NOT NULL,
    sport_match_id integer NOT NULL,
    event_type character varying(50) NOT NULL,
    team character varying(10) NOT NULL,
    player_name character varying(255) DEFAULT NULL::character varying,
    second_player_name character varying(255) DEFAULT NULL::character varying,
    event_minute integer NOT NULL,
    extra_time_minute integer,
    score_after_event character varying(20) DEFAULT NULL::character varying,
    description text,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_match_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_match_events_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_match_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_match_events_id_seq OWNED BY public.live_text_match_events.id;


--
-- Name: live_text_post_engagements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_post_engagements (
    id integer NOT NULL,
    post_id integer NOT NULL,
    user_id integer,
    session_id character varying(255) NOT NULL,
    engagement_type character varying(50) NOT NULL,
    time_spent integer,
    scroll_depth integer,
    clicked_element character varying(255) DEFAULT NULL::character varying,
    metadata json,
    ip_address character varying(45) DEFAULT NULL::character varying,
    user_agent character varying(500) DEFAULT NULL::character varying,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_post_engagements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_post_engagements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_post_engagements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_post_engagements_id_seq OWNED BY public.live_text_post_engagements.id;


--
-- Name: live_text_posts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_posts (
    id integer NOT NULL,
    live_text_id integer NOT NULL,
    author_id integer NOT NULL,
    content text NOT NULL,
    content_html text,
    is_key_point boolean DEFAULT false NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    published_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_posts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_posts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_posts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_posts_id_seq OWNED BY public.live_text_posts.id;


--
-- Name: live_text_reactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_reactions (
    id integer NOT NULL,
    live_text_post_id integer NOT NULL,
    user_id integer,
    reaction_type character varying(20) NOT NULL,
    ip_address character varying(45) DEFAULT NULL::character varying,
    user_agent character varying(255) DEFAULT NULL::character varying,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_reactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_reactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_reactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_reactions_id_seq OWNED BY public.live_text_reactions.id;


--
-- Name: live_text_sport_matches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_sport_matches (
    id integer NOT NULL,
    live_text_id integer NOT NULL,
    sport_type character varying(50) NOT NULL,
    home_team character varying(255) NOT NULL,
    away_team character varying(255) NOT NULL,
    home_team_logo character varying(500) DEFAULT NULL::character varying,
    away_team_logo character varying(500) DEFAULT NULL::character varying,
    home_score integer NOT NULL,
    away_score integer NOT NULL,
    status character varying(30) NOT NULL,
    current_minute integer,
    current_period character varying(50) DEFAULT NULL::character varying,
    venue character varying(255) DEFAULT NULL::character varying,
    competition character varying(255) DEFAULT NULL::character varying,
    scheduled_start_time timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    actual_start_time timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    end_time timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    statistics json,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_sport_matches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_sport_matches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_sport_matches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_sport_matches_id_seq OWNED BY public.live_text_sport_matches.id;


--
-- Name: live_text_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_templates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    type character varying(255) NOT NULL,
    config json NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_templates_id_seq OWNED BY public.live_text_templates.id;


--
-- Name: live_text_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_text_views (
    id integer NOT NULL,
    live_text_id integer NOT NULL,
    user_id integer,
    session_id character varying(255) NOT NULL,
    time_spent integer DEFAULT 0 NOT NULL,
    ip_address character varying(45) DEFAULT NULL::character varying,
    user_agent text,
    viewed_at timestamp(0) without time zone NOT NULL,
    last_activity_at timestamp(0) without time zone NOT NULL
);


--
-- Name: live_text_views_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_text_views_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_text_views_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_text_views_id_seq OWNED BY public.live_text_views.id;


--
-- Name: live_texts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.live_texts (
    id integer NOT NULL,
    author_id integer NOT NULL,
    category_id integer,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    status character varying(50) NOT NULL,
    start_time timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    end_time timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL,
    template_id integer
);


--
-- Name: live_texts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.live_texts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: live_texts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.live_texts_id_seq OWNED BY public.live_texts.id;


--
-- Name: messenger_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messenger_messages (
    id bigint NOT NULL,
    body text NOT NULL,
    headers text NOT NULL,
    queue_name character varying(190) NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    available_at timestamp(0) without time zone NOT NULL,
    delivered_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);


--
-- Name: COLUMN messenger_messages.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.messenger_messages.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN messenger_messages.available_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.messenger_messages.available_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN messenger_messages.delivered_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)';


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messenger_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messenger_messages_id_seq OWNED BY public.messenger_messages.id;


--
-- Name: page_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.page_views (
    id bigint NOT NULL,
    article_id integer,
    visitor_id character varying(255) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    referrer text,
    category_id integer,
    viewed_at timestamp without time zone NOT NULL,
    session_duration integer
);


--
-- Name: page_views_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.page_views_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: page_views_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.page_views_id_seq OWNED BY public.page_views.id;


--
-- Name: refresh_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.refresh_tokens (
    id integer NOT NULL,
    refresh_token character varying(128) NOT NULL,
    username character varying(255) NOT NULL,
    valid timestamp(0) without time zone NOT NULL
);


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.refresh_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.refresh_tokens_id_seq OWNED BY public.refresh_tokens.id;


--
-- Name: related_articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.related_articles (
    article_id integer NOT NULL,
    related_article_id integer NOT NULL
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id uuid NOT NULL,
    visitor_id character varying(255) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    referrer text,
    started_at timestamp without time zone NOT NULL,
    ended_at timestamp without time zone,
    page_count integer DEFAULT 0 NOT NULL,
    duration integer
);


--
-- Name: site_stats_daily; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site_stats_daily (
    id integer NOT NULL,
    date date NOT NULL,
    total_visits integer DEFAULT 0 NOT NULL,
    unique_visitors integer DEFAULT 0 NOT NULL,
    new_visitors integer DEFAULT 0 NOT NULL,
    bounce_rate numeric(5,2),
    avg_session_duration integer
);


--
-- Name: site_stats_daily_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.site_stats_daily_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: site_stats_daily_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.site_stats_daily_id_seq OWNED BY public.site_stats_daily.id;


--
-- Name: thumbnail_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.thumbnail_profiles (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    display_name character varying(255) NOT NULL,
    description text,
    width integer NOT NULL,
    height integer NOT NULL,
    aspect_ratio character varying(10) DEFAULT NULL::character varying,
    mode character varying(20) NOT NULL,
    quality integer NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    category character varying(20) NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN thumbnail_profiles.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.thumbnail_profiles.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN thumbnail_profiles.updated_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.thumbnail_profiles.updated_at IS '(DC2Type:datetime_immutable)';


--
-- Name: thumbnail_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.thumbnail_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: thumbnail_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.thumbnail_profiles_id_seq OWNED BY public.thumbnail_profiles.id;


--
-- Name: thumbnails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.thumbnails (
    id integer NOT NULL,
    image_id integer NOT NULL,
    profile_id integer NOT NULL,
    filename character varying(255) NOT NULL,
    path character varying(500) NOT NULL,
    width integer NOT NULL,
    height integer NOT NULL,
    size integer NOT NULL,
    crop_data json,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: COLUMN thumbnails.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.thumbnails.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: thumbnails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.thumbnails_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: thumbnails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.thumbnails_id_seq OWNED BY public.thumbnails.id;


--
-- Name: url_redirects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.url_redirects (
    id integer NOT NULL,
    old_url character varying(500) NOT NULL,
    new_url character varying(500) NOT NULL,
    locale character varying(10) NOT NULL,
    http_status_code integer NOT NULL,
    type character varying(50) NOT NULL,
    entity_id integer,
    hit_count integer NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    last_accessed_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);


--
-- Name: COLUMN url_redirects.created_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.url_redirects.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN url_redirects.last_accessed_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.url_redirects.last_accessed_at IS '(DC2Type:datetime_immutable)';


--
-- Name: url_redirects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_redirects_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_redirects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.url_redirects_id_seq OWNED BY public.url_redirects.id;


--
-- Name: user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public."user" (
    id integer NOT NULL,
    username character varying(180) NOT NULL,
    email character varying(180) NOT NULL,
    first_name character varying(100) DEFAULT NULL::character varying,
    last_name character varying(100) DEFAULT NULL::character varying,
    roles json NOT NULL,
    password character varying(255) NOT NULL
);


--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_id_seq OWNED BY public."user".id;


--
-- Name: article_image id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_image ALTER COLUMN id SET DEFAULT nextval('public.article_image_id_seq'::regclass);


--
-- Name: article_locks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_locks ALTER COLUMN id SET DEFAULT nextval('public.article_locks_id_seq'::regclass);


--
-- Name: article_stats_daily id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_stats_daily ALTER COLUMN id SET DEFAULT nextval('public.article_stats_daily_id_seq'::regclass);


--
-- Name: articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles ALTER COLUMN id SET DEFAULT nextval('public.articles_id_seq'::regclass);


--
-- Name: authors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authors ALTER COLUMN id SET DEFAULT nextval('public.authors_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: ext_translations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ext_translations ALTER COLUMN id SET DEFAULT nextval('public.ext_translations_id_seq'::regclass);


--
-- Name: external_article_mappings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.external_article_mappings ALTER COLUMN id SET DEFAULT nextval('public.external_article_mappings_id_seq'::regclass);


--
-- Name: images id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images ALTER COLUMN id SET DEFAULT nextval('public.images_id_seq'::regclass);


--
-- Name: important_articles_list id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.important_articles_list ALTER COLUMN id SET DEFAULT nextval('public.important_articles_list_id_seq'::regclass);


--
-- Name: live_text_ab_tests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_tests ALTER COLUMN id SET DEFAULT nextval('public.live_text_ab_tests_id_seq'::regclass);


--
-- Name: live_text_collaborators id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_collaborators ALTER COLUMN id SET DEFAULT nextval('public.live_text_collaborators_id_seq'::regclass);


--
-- Name: live_text_match_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_match_events ALTER COLUMN id SET DEFAULT nextval('public.live_text_match_events_id_seq'::regclass);


--
-- Name: live_text_post_engagements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_post_engagements ALTER COLUMN id SET DEFAULT nextval('public.live_text_post_engagements_id_seq'::regclass);


--
-- Name: live_text_posts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_posts ALTER COLUMN id SET DEFAULT nextval('public.live_text_posts_id_seq'::regclass);


--
-- Name: live_text_reactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_reactions ALTER COLUMN id SET DEFAULT nextval('public.live_text_reactions_id_seq'::regclass);


--
-- Name: live_text_sport_matches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_sport_matches ALTER COLUMN id SET DEFAULT nextval('public.live_text_sport_matches_id_seq'::regclass);


--
-- Name: live_text_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_templates ALTER COLUMN id SET DEFAULT nextval('public.live_text_templates_id_seq'::regclass);


--
-- Name: live_text_views id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_views ALTER COLUMN id SET DEFAULT nextval('public.live_text_views_id_seq'::regclass);


--
-- Name: live_texts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_texts ALTER COLUMN id SET DEFAULT nextval('public.live_texts_id_seq'::regclass);


--
-- Name: messenger_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_messages ALTER COLUMN id SET DEFAULT nextval('public.messenger_messages_id_seq'::regclass);


--
-- Name: page_views id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views ALTER COLUMN id SET DEFAULT nextval('public.page_views_id_seq'::regclass);


--
-- Name: refresh_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens ALTER COLUMN id SET DEFAULT nextval('public.refresh_tokens_id_seq'::regclass);


--
-- Name: site_stats_daily id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_stats_daily ALTER COLUMN id SET DEFAULT nextval('public.site_stats_daily_id_seq'::regclass);


--
-- Name: thumbnail_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnail_profiles ALTER COLUMN id SET DEFAULT nextval('public.thumbnail_profiles_id_seq'::regclass);


--
-- Name: thumbnails id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnails ALTER COLUMN id SET DEFAULT nextval('public.thumbnails_id_seq'::regclass);


--
-- Name: url_redirects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_redirects ALTER COLUMN id SET DEFAULT nextval('public.url_redirects_id_seq'::regclass);


--
-- Name: user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."user" ALTER COLUMN id SET DEFAULT nextval('public.user_id_seq'::regclass);


--
-- Name: article_author article_author_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_author
    ADD CONSTRAINT article_author_pkey PRIMARY KEY (article_id, author_id);


--
-- Name: article_image article_image_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_image
    ADD CONSTRAINT article_image_pkey PRIMARY KEY (id);


--
-- Name: article_locks article_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_locks
    ADD CONSTRAINT article_locks_pkey PRIMARY KEY (id);


--
-- Name: article_stats_daily article_stats_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_stats_daily
    ADD CONSTRAINT article_stats_daily_pkey PRIMARY KEY (id);


--
-- Name: articles articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_pkey PRIMARY KEY (id);


--
-- Name: authors authors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authors
    ADD CONSTRAINT authors_pkey PRIMARY KEY (id);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: doctrine_migration_versions doctrine_migration_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.doctrine_migration_versions
    ADD CONSTRAINT doctrine_migration_versions_pkey PRIMARY KEY (version);


--
-- Name: ext_translations ext_translations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ext_translations
    ADD CONSTRAINT ext_translations_pkey PRIMARY KEY (id);


--
-- Name: external_article_mappings external_article_mappings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.external_article_mappings
    ADD CONSTRAINT external_article_mappings_pkey PRIMARY KEY (id);


--
-- Name: images images_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_pkey PRIMARY KEY (id);


--
-- Name: important_articles_list important_articles_list_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.important_articles_list
    ADD CONSTRAINT important_articles_list_pkey PRIMARY KEY (id);


--
-- Name: live_text_ab_test_live_texts live_text_ab_test_live_texts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_test_live_texts
    ADD CONSTRAINT live_text_ab_test_live_texts_pkey PRIMARY KEY (live_text_ab_test_id, live_text_id);


--
-- Name: live_text_ab_tests live_text_ab_tests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_tests
    ADD CONSTRAINT live_text_ab_tests_pkey PRIMARY KEY (id);


--
-- Name: live_text_collaborators live_text_collaborators_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_collaborators
    ADD CONSTRAINT live_text_collaborators_pkey PRIMARY KEY (id);


--
-- Name: live_text_match_events live_text_match_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_match_events
    ADD CONSTRAINT live_text_match_events_pkey PRIMARY KEY (id);


--
-- Name: live_text_post_engagements live_text_post_engagements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_post_engagements
    ADD CONSTRAINT live_text_post_engagements_pkey PRIMARY KEY (id);


--
-- Name: live_text_posts live_text_posts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_posts
    ADD CONSTRAINT live_text_posts_pkey PRIMARY KEY (id);


--
-- Name: live_text_reactions live_text_reactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_reactions
    ADD CONSTRAINT live_text_reactions_pkey PRIMARY KEY (id);


--
-- Name: live_text_sport_matches live_text_sport_matches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_sport_matches
    ADD CONSTRAINT live_text_sport_matches_pkey PRIMARY KEY (id);


--
-- Name: live_text_templates live_text_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_templates
    ADD CONSTRAINT live_text_templates_pkey PRIMARY KEY (id);


--
-- Name: live_text_views live_text_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_views
    ADD CONSTRAINT live_text_views_pkey PRIMARY KEY (id);


--
-- Name: live_texts live_texts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_texts
    ADD CONSTRAINT live_texts_pkey PRIMARY KEY (id);


--
-- Name: messenger_messages messenger_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messenger_messages
    ADD CONSTRAINT messenger_messages_pkey PRIMARY KEY (id);


--
-- Name: page_views page_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views
    ADD CONSTRAINT page_views_pkey PRIMARY KEY (id);


--
-- Name: refresh_tokens refresh_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_pkey PRIMARY KEY (id);


--
-- Name: related_articles related_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_articles
    ADD CONSTRAINT related_articles_pkey PRIMARY KEY (article_id, related_article_id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: site_stats_daily site_stats_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_stats_daily
    ADD CONSTRAINT site_stats_daily_pkey PRIMARY KEY (id);


--
-- Name: thumbnail_profiles thumbnail_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnail_profiles
    ADD CONSTRAINT thumbnail_profiles_pkey PRIMARY KEY (id);


--
-- Name: thumbnails thumbnails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnails
    ADD CONSTRAINT thumbnails_pkey PRIMARY KEY (id);


--
-- Name: article_stats_daily uniq_article_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_stats_daily
    ADD CONSTRAINT uniq_article_date UNIQUE (article_id, date);


--
-- Name: site_stats_daily uniq_site_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_stats_daily
    ADD CONSTRAINT uniq_site_date UNIQUE (date);


--
-- Name: url_redirects url_redirects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_redirects
    ADD CONSTRAINT url_redirects_pkey PRIMARY KEY (id);


--
-- Name: user user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: idx_17fb4086a76ed395; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_17fb4086a76ed395 ON public.live_text_views USING btree (user_id);


--
-- Name: idx_195e7fc57294869c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_195e7fc57294869c ON public.related_articles USING btree (article_id);


--
-- Name: idx_195e7fc5f8598e2c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_195e7fc5f8598e2c ON public.related_articles USING btree (related_article_id);


--
-- Name: idx_36d47c7ea76ed395; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_36d47c7ea76ed395 ON public.live_text_post_engagements USING btree (user_id);


--
-- Name: idx_4eef1ea212469de2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_4eef1ea212469de2 ON public.live_texts USING btree (category_id);


--
-- Name: idx_4eef1ea25da0fb8; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_4eef1ea25da0fb8 ON public.live_texts USING btree (template_id);


--
-- Name: idx_4eef1ea2f675f31b; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_4eef1ea2f675f31b ON public.live_texts USING btree (author_id);


--
-- Name: idx_588a6a277294869c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_588a6a277294869c ON public.external_article_mappings USING btree (article_id);


--
-- Name: idx_63d817661c1c536c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_63d817661c1c536c ON public.live_text_match_events USING btree (sport_match_id);


--
-- Name: idx_75ea56e016ba31db; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_75ea56e016ba31db ON public.messenger_messages USING btree (delivered_at);


--
-- Name: idx_75ea56e0e3bd61ce; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_75ea56e0e3bd61ce ON public.messenger_messages USING btree (available_at);


--
-- Name: idx_75ea56e0fb7336f0; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_75ea56e0fb7336f0 ON public.messenger_messages USING btree (queue_name);


--
-- Name: idx_793f398a76ed395; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_793f398a76ed395 ON public.live_text_collaborators USING btree (user_id);


--
-- Name: idx_793f398e8507533; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_793f398e8507533 ON public.live_text_collaborators USING btree (live_text_id);


--
-- Name: idx_8b861f867a88e00; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_8b861f867a88e00 ON public.article_locks USING btree (locked_by_id);


--
-- Name: idx_8e5666fbf675f31b; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_8e5666fbf675f31b ON public.live_text_posts USING btree (author_id);


--
-- Name: idx_ab_test_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ab_test_start ON public.live_text_ab_tests USING btree (start_date);


--
-- Name: idx_ab_test_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ab_test_status ON public.live_text_ab_tests USING btree (status);


--
-- Name: idx_article_category_status_published; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_category_status_published ON public.articles USING btree (category_id, status, published_at);


--
-- Name: idx_article_featured; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_featured ON public.articles USING btree (is_featured);


--
-- Name: idx_article_featured_published; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_featured_published ON public.articles USING btree (is_featured, published_at);


--
-- Name: idx_article_image_article; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_image_article ON public.article_image USING btree (article_id);


--
-- Name: idx_article_image_featured; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_image_featured ON public.article_image USING btree (article_id, is_featured);


--
-- Name: idx_article_image_image; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_image_image ON public.article_image USING btree (image_id);


--
-- Name: idx_article_image_position; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_image_position ON public.article_image USING btree (article_id, "position");


--
-- Name: idx_article_image_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_article_image_unique ON public.article_image USING btree (article_id, image_id);


--
-- Name: idx_article_lock_article; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_lock_article ON public.article_locks USING btree (article_id);


--
-- Name: idx_article_lock_expires; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_lock_expires ON public.article_locks USING btree (expires_at);


--
-- Name: idx_article_publish_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_publish_at ON public.articles USING btree (publish_at);


--
-- Name: idx_article_published_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_published_at ON public.articles USING btree (published_at);


--
-- Name: idx_article_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_status ON public.articles USING btree (status);


--
-- Name: idx_article_status_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_status_category ON public.articles USING btree (status, category_id);


--
-- Name: idx_article_status_published; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_status_published ON public.articles USING btree (status, published_at);


--
-- Name: idx_article_views; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_article_views ON public.page_views USING btree (article_id, viewed_at);


--
-- Name: idx_author_active_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_author_active_status ON public.authors USING btree (is_active, status);


--
-- Name: idx_author_email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_author_email ON public.authors USING btree (email);


--
-- Name: idx_author_is_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_author_is_active ON public.authors USING btree (is_active);


--
-- Name: idx_author_slug; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_author_slug ON public.authors USING btree (slug);


--
-- Name: idx_author_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_author_status ON public.authors USING btree (status);


--
-- Name: idx_bfdd316812469de2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bfdd316812469de2 ON public.articles USING btree (category_id);


--
-- Name: idx_c6f824fcabe0f3a1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_c6f824fcabe0f3a1 ON public.live_text_ab_test_live_texts USING btree (live_text_ab_test_id);


--
-- Name: idx_c6f824fce8507533; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_c6f824fce8507533 ON public.live_text_ab_test_live_texts USING btree (live_text_id);


--
-- Name: idx_category_on_front_page; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_category_on_front_page ON public.categories USING btree (on_front_page);


--
-- Name: idx_category_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_category_status ON public.categories USING btree (status);


--
-- Name: idx_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_created_at ON public.url_redirects USING btree (created_at);


--
-- Name: idx_d7684f487294869c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_d7684f487294869c ON public.article_author USING btree (article_id);


--
-- Name: idx_d7684f48f675f31b; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_d7684f48f675f31b ON public.article_author USING btree (author_id);


--
-- Name: idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_date ON public.article_stats_daily USING btree (date);


--
-- Name: idx_eacdb8b27294869c; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_eacdb8b27294869c ON public.important_articles_list USING btree (article_id);


--
-- Name: idx_entity_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_entity_type ON public.url_redirects USING btree (type, entity_id);


--
-- Name: idx_f74daa4bb03a8386; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_f74daa4bb03a8386 ON public.live_text_ab_tests USING btree (created_by_id);


--
-- Name: idx_image_filename; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_image_filename ON public.images USING btree (filename);


--
-- Name: idx_image_profile_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_image_profile_unique ON public.thumbnails USING btree (image_id, profile_id);


--
-- Name: idx_important_articles_position; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_important_articles_position ON public.important_articles_list USING btree ("position");


--
-- Name: idx_live_text_end_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_end_time ON public.live_texts USING btree (end_time);


--
-- Name: idx_live_text_post_is_key_point; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_post_is_key_point ON public.live_text_posts USING btree (is_key_point);


--
-- Name: idx_live_text_post_live_text; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_post_live_text ON public.live_text_posts USING btree (live_text_id);


--
-- Name: idx_live_text_post_position; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_post_position ON public.live_text_posts USING btree ("position");


--
-- Name: idx_live_text_post_published_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_post_published_at ON public.live_text_posts USING btree (published_at);


--
-- Name: idx_live_text_start_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_start_time ON public.live_texts USING btree (start_time);


--
-- Name: idx_live_text_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_status ON public.live_texts USING btree (status);


--
-- Name: idx_live_text_view_ip; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_view_ip ON public.live_text_views USING btree (ip_address);


--
-- Name: idx_live_text_view_live_text; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_view_live_text ON public.live_text_views USING btree (live_text_id);


--
-- Name: idx_live_text_view_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_view_session ON public.live_text_views USING btree (session_id);


--
-- Name: idx_live_text_view_viewed_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_live_text_view_viewed_at ON public.live_text_views USING btree (viewed_at);


--
-- Name: idx_livetext_category_status_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_livetext_category_status_start ON public.live_texts USING btree (category_id, status, start_time);


--
-- Name: idx_livetext_status_end; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_livetext_status_end ON public.live_texts USING btree (status, end_time);


--
-- Name: idx_livetext_status_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_livetext_status_start ON public.live_texts USING btree (status, start_time);


--
-- Name: idx_old_url; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_old_url ON public.url_redirects USING btree (old_url);


--
-- Name: idx_post_engagement_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_post_engagement_created ON public.live_text_post_engagements USING btree (created_at);


--
-- Name: idx_post_engagement_post; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_post_engagement_post ON public.live_text_post_engagements USING btree (post_id);


--
-- Name: idx_post_engagement_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_post_engagement_session ON public.live_text_post_engagements USING btree (session_id);


--
-- Name: idx_profile_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_profile_category ON public.thumbnail_profiles USING btree (category);


--
-- Name: idx_profile_dimensions; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_profile_dimensions ON public.thumbnail_profiles USING btree (width, height, mode);


--
-- Name: idx_profile_is_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_profile_is_active ON public.thumbnail_profiles USING btree (is_active);


--
-- Name: idx_profile_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_profile_name ON public.thumbnail_profiles USING btree (name);


--
-- Name: idx_reaction_ip; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reaction_ip ON public.live_text_reactions USING btree (ip_address);


--
-- Name: idx_reaction_post; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reaction_post ON public.live_text_reactions USING btree (live_text_post_id);


--
-- Name: idx_reaction_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reaction_type ON public.live_text_reactions USING btree (reaction_type);


--
-- Name: idx_reaction_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reaction_user ON public.live_text_reactions USING btree (user_id);


--
-- Name: idx_site_stats_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_site_stats_date ON public.site_stats_daily USING btree (date);


--
-- Name: idx_started_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_started_at ON public.sessions USING btree (started_at);


--
-- Name: idx_template_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_template_type ON public.live_text_templates USING btree (type);


--
-- Name: idx_thumbnail_image; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_thumbnail_image ON public.thumbnails USING btree (image_id);


--
-- Name: idx_thumbnail_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_thumbnail_profile ON public.thumbnails USING btree (profile_id);


--
-- Name: idx_viewed_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_viewed_at ON public.page_views USING btree (viewed_at);


--
-- Name: idx_visitor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_visitor ON public.page_views USING btree (visitor_id);


--
-- Name: idx_visitor_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_visitor_id ON public.sessions USING btree (visitor_id);


--
-- Name: lookup_unique_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX lookup_unique_idx ON public.ext_translations USING btree (foreign_key, locale, object_class, field);


--
-- Name: uniq_3af34668989d9b62; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_3af34668989d9b62 ON public.categories USING btree (slug);


--
-- Name: uniq_4eef1ea2989d9b62; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_4eef1ea2989d9b62 ON public.live_texts USING btree (slug);


--
-- Name: uniq_8d93d649e7927c74; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_8d93d649e7927c74 ON public."user" USING btree (email);


--
-- Name: uniq_8e0c2a51989d9b62; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_8e0c2a51989d9b62 ON public.authors USING btree (slug);


--
-- Name: uniq_8e0c2a51e7927c74; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_8e0c2a51e7927c74 ON public.authors USING btree (email);


--
-- Name: uniq_9bace7e1c74f2195; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_9bace7e1c74f2195 ON public.refresh_tokens USING btree (refresh_token);


--
-- Name: uniq_a2cbe8c35e237e06; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_a2cbe8c35e237e06 ON public.thumbnail_profiles USING btree (name);


--
-- Name: uniq_bfdd3168989d9b62; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_bfdd3168989d9b62 ON public.articles USING btree (slug);


--
-- Name: uniq_e01fbe6a3c0be965; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_e01fbe6a3c0be965 ON public.images USING btree (filename);


--
-- Name: uniq_e2dd35dce8507533; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_e2dd35dce8507533 ON public.live_text_sport_matches USING btree (live_text_id);


--
-- Name: uniq_identifier_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_identifier_username ON public."user" USING btree (username);


--
-- Name: uniq_source_external_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uniq_source_external_id ON public.external_article_mappings USING btree (source, external_id);


--
-- Name: unique_ip_post_reaction; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX unique_ip_post_reaction ON public.live_text_reactions USING btree (live_text_post_id, ip_address);


--
-- Name: unique_live_text_user; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX unique_live_text_user ON public.live_text_collaborators USING btree (live_text_id, user_id);


--
-- Name: unique_user_post_reaction; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX unique_user_post_reaction ON public.live_text_reactions USING btree (live_text_post_id, user_id);


--
-- Name: messenger_messages notify_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON public.messenger_messages FOR EACH ROW EXECUTE FUNCTION public.notify_messenger_messages();


--
-- Name: article_stats_daily article_stats_daily_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_stats_daily
    ADD CONSTRAINT article_stats_daily_article_id_fkey FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: live_text_views fk_17fb4086a76ed395; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_views
    ADD CONSTRAINT fk_17fb4086a76ed395 FOREIGN KEY (user_id) REFERENCES public."user"(id) ON DELETE SET NULL;


--
-- Name: live_text_views fk_17fb4086e8507533; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_views
    ADD CONSTRAINT fk_17fb4086e8507533 FOREIGN KEY (live_text_id) REFERENCES public.live_texts(id) ON DELETE CASCADE;


--
-- Name: related_articles fk_195e7fc57294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_articles
    ADD CONSTRAINT fk_195e7fc57294869c FOREIGN KEY (article_id) REFERENCES public.articles(id);


--
-- Name: related_articles fk_195e7fc5f8598e2c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.related_articles
    ADD CONSTRAINT fk_195e7fc5f8598e2c FOREIGN KEY (related_article_id) REFERENCES public.articles(id);


--
-- Name: live_text_post_engagements fk_36d47c7e4b89032c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_post_engagements
    ADD CONSTRAINT fk_36d47c7e4b89032c FOREIGN KEY (post_id) REFERENCES public.live_text_posts(id);


--
-- Name: live_text_post_engagements fk_36d47c7ea76ed395; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_post_engagements
    ADD CONSTRAINT fk_36d47c7ea76ed395 FOREIGN KEY (user_id) REFERENCES public."user"(id);


--
-- Name: live_texts fk_4eef1ea212469de2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_texts
    ADD CONSTRAINT fk_4eef1ea212469de2 FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: live_texts fk_4eef1ea25da0fb8; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_texts
    ADD CONSTRAINT fk_4eef1ea25da0fb8 FOREIGN KEY (template_id) REFERENCES public.live_text_templates(id) ON DELETE SET NULL;


--
-- Name: live_texts fk_4eef1ea2f675f31b; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_texts
    ADD CONSTRAINT fk_4eef1ea2f675f31b FOREIGN KEY (author_id) REFERENCES public."user"(id);


--
-- Name: thumbnails fk_52a4df603da5256d; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnails
    ADD CONSTRAINT fk_52a4df603da5256d FOREIGN KEY (image_id) REFERENCES public.images(id) ON DELETE CASCADE;


--
-- Name: thumbnails fk_52a4df60ccfa12b8; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.thumbnails
    ADD CONSTRAINT fk_52a4df60ccfa12b8 FOREIGN KEY (profile_id) REFERENCES public.thumbnail_profiles(id) ON DELETE RESTRICT;


--
-- Name: external_article_mappings fk_588a6a277294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.external_article_mappings
    ADD CONSTRAINT fk_588a6a277294869c FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: live_text_match_events fk_63d817661c1c536c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_match_events
    ADD CONSTRAINT fk_63d817661c1c536c FOREIGN KEY (sport_match_id) REFERENCES public.live_text_sport_matches(id);


--
-- Name: live_text_collaborators fk_793f398a76ed395; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_collaborators
    ADD CONSTRAINT fk_793f398a76ed395 FOREIGN KEY (user_id) REFERENCES public."user"(id);


--
-- Name: live_text_collaborators fk_793f398e8507533; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_collaborators
    ADD CONSTRAINT fk_793f398e8507533 FOREIGN KEY (live_text_id) REFERENCES public.live_texts(id);


--
-- Name: article_locks fk_8b861f867294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_locks
    ADD CONSTRAINT fk_8b861f867294869c FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: article_locks fk_8b861f867a88e00; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_locks
    ADD CONSTRAINT fk_8b861f867a88e00 FOREIGN KEY (locked_by_id) REFERENCES public."user"(id) ON DELETE CASCADE;


--
-- Name: live_text_posts fk_8e5666fbe8507533; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_posts
    ADD CONSTRAINT fk_8e5666fbe8507533 FOREIGN KEY (live_text_id) REFERENCES public.live_texts(id);


--
-- Name: live_text_posts fk_8e5666fbf675f31b; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_posts
    ADD CONSTRAINT fk_8e5666fbf675f31b FOREIGN KEY (author_id) REFERENCES public."user"(id);


--
-- Name: article_image fk_b28a764e3da5256d; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_image
    ADD CONSTRAINT fk_b28a764e3da5256d FOREIGN KEY (image_id) REFERENCES public.images(id) ON DELETE CASCADE;


--
-- Name: article_image fk_b28a764e7294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_image
    ADD CONSTRAINT fk_b28a764e7294869c FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: articles fk_bfdd316812469de2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT fk_bfdd316812469de2 FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: live_text_ab_test_live_texts fk_c6f824fcabe0f3a1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_test_live_texts
    ADD CONSTRAINT fk_c6f824fcabe0f3a1 FOREIGN KEY (live_text_ab_test_id) REFERENCES public.live_text_ab_tests(id) ON DELETE CASCADE;


--
-- Name: live_text_ab_test_live_texts fk_c6f824fce8507533; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_test_live_texts
    ADD CONSTRAINT fk_c6f824fce8507533 FOREIGN KEY (live_text_id) REFERENCES public.live_texts(id) ON DELETE CASCADE;


--
-- Name: article_author fk_d7684f487294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_author
    ADD CONSTRAINT fk_d7684f487294869c FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: article_author fk_d7684f48f675f31b; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.article_author
    ADD CONSTRAINT fk_d7684f48f675f31b FOREIGN KEY (author_id) REFERENCES public.authors(id) ON DELETE CASCADE;


--
-- Name: live_text_sport_matches fk_e2dd35dce8507533; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_sport_matches
    ADD CONSTRAINT fk_e2dd35dce8507533 FOREIGN KEY (live_text_id) REFERENCES public.live_texts(id);


--
-- Name: important_articles_list fk_eacdb8b27294869c; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.important_articles_list
    ADD CONSTRAINT fk_eacdb8b27294869c FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: live_text_reactions fk_ecba87ca76ed395; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_reactions
    ADD CONSTRAINT fk_ecba87ca76ed395 FOREIGN KEY (user_id) REFERENCES public."user"(id) ON DELETE SET NULL;


--
-- Name: live_text_reactions fk_ecba87cddf7aa99; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_reactions
    ADD CONSTRAINT fk_ecba87cddf7aa99 FOREIGN KEY (live_text_post_id) REFERENCES public.live_text_posts(id) ON DELETE CASCADE;


--
-- Name: live_text_ab_tests fk_f74daa4bb03a8386; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.live_text_ab_tests
    ADD CONSTRAINT fk_f74daa4bb03a8386 FOREIGN KEY (created_by_id) REFERENCES public."user"(id);


--
-- Name: page_views page_views_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views
    ADD CONSTRAINT page_views_article_id_fkey FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE SET NULL;


--
-- Name: page_views page_views_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_views
    ADD CONSTRAINT page_views_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict bs6gXXtmg4kDhigoaXqoVhnuqcf6dtfawgVVjsAt959jStryiVtZqpOpoV6A09m

