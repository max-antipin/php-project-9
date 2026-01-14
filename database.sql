CREATE TABLE public.urls (
	id uuid DEFAULT gen_random_uuid() NOT NULL,
	name varchar(100) NOT NULL,
	created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
	CONSTRAINT urls_pk PRIMARY KEY (id),
	CONSTRAINT urls_unique UNIQUE (name)
);

CREATE TABLE public.url_checks (
	id uuid DEFAULT gen_random_uuid() NOT NULL,
	url_id uuid NOT NULL,
	status_code int4 NOT NULL,
	h1 varchar NOT NULL,
	title varchar NOT NULL,
	description text NOT NULL,
	created_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
	CONSTRAINT url_checks_urls_fk FOREIGN KEY (url_id) REFERENCES public.urls(id) ON DELETE CASCADE ON UPDATE CASCADE
);