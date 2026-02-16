-- PostgreSQL schema for the PHP app (run in Supabase SQL editor)
-- This keeps the current PHP auth/session flow and table names.

create schema if not exists phpapp;
set search_path to phpapp, public;

create table if not exists users (
  id integer generated always as identity primary key,
  name varchar(120) not null,
  email varchar(180) not null unique,
  password_hash varchar(255) not null,
  role text not null default 'student' check (role in ('admin', 'instructor', 'student')),
  is_active smallint not null default 1 check (is_active in (0, 1)),
  created_at timestamptz not null default now()
);

create table if not exists categories (
  id integer generated always as identity primary key,
  name varchar(120) not null unique,
  created_at timestamptz not null default now()
);

create table if not exists courses (
  id integer generated always as identity primary key,
  instructor_id integer not null references users(id),
  category_id integer null references categories(id),
  title varchar(180) not null,
  slug varchar(220) not null unique,
  description text not null,
  thumbnail_url varchar(500) null,
  price numeric(10,2) not null default 0.00,
  level text not null default 'beginner' check (level in ('beginner', 'intermediate', 'advanced')),
  status text not null default 'draft' check (status in ('draft', 'pending', 'approved', 'rejected')),
  rejection_reason text null,
  published_at timestamptz null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists course_sections (
  id integer generated always as identity primary key,
  course_id integer not null references courses(id) on delete cascade,
  title varchar(180) not null,
  sort_order integer not null default 0,
  created_at timestamptz not null default now()
);

create table if not exists course_lessons (
  id integer generated always as identity primary key,
  section_id integer not null references course_sections(id) on delete cascade,
  title varchar(180) not null,
  content_type text not null default 'video' check (content_type in ('video', 'text')),
  video_url varchar(500) null,
  body_text text null,
  duration_seconds integer not null default 0,
  sort_order integer not null default 0,
  is_preview smallint not null default 0 check (is_preview in (0, 1)),
  created_at timestamptz not null default now()
);

create table if not exists enrollments (
  id integer generated always as identity primary key,
  student_id integer not null references users(id),
  course_id integer not null references courses(id) on delete cascade,
  payment_status text not null default 'free' check (payment_status in ('free', 'mock_paid', 'stripe_paid', 'razorpay_paid')),
  payment_ref varchar(190) null,
  enrolled_at timestamptz not null default now(),
  unique (student_id, course_id)
);

create table if not exists lesson_progress (
  id integer generated always as identity primary key,
  student_id integer not null references users(id),
  lesson_id integer not null references course_lessons(id) on delete cascade,
  is_completed smallint not null default 1 check (is_completed in (0, 1)),
  completed_at timestamptz not null default now(),
  unique (student_id, lesson_id)
);

create table if not exists reviews (
  id integer generated always as identity primary key,
  student_id integer not null references users(id),
  course_id integer not null references courses(id) on delete cascade,
  rating smallint not null check (rating between 1 and 5),
  comment text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (student_id, course_id)
);

create table if not exists payments (
  id integer generated always as identity primary key,
  student_id integer not null references users(id),
  course_id integer not null references courses(id) on delete cascade,
  provider text not null default 'mock' check (provider in ('mock', 'stripe', 'razorpay')),
  amount numeric(10,2) not null,
  currency char(3) not null default 'USD',
  status text not null default 'pending' check (status in ('pending', 'success', 'failed')),
  provider_ref varchar(190) null,
  metadata_json jsonb null,
  created_at timestamptz not null default now()
);

create table if not exists course_approval_logs (
  id integer generated always as identity primary key,
  course_id integer not null references courses(id) on delete cascade,
  admin_id integer not null references users(id),
  action text not null check (action in ('approved', 'rejected')),
  note text null,
  created_at timestamptz not null default now()
);

create or replace function phpapp.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists trg_courses_updated_at on courses;
create trigger trg_courses_updated_at
before update on courses
for each row
execute function phpapp.set_updated_at();

drop trigger if exists trg_reviews_updated_at on reviews;
create trigger trg_reviews_updated_at
before update on reviews
for each row
execute function phpapp.set_updated_at();

insert into categories (name)
values ('Web Development'), ('Data Science'), ('Design'), ('Marketing')
on conflict (name) do nothing;
