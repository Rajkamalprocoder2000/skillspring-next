-- SkillSpring schema for Supabase (Postgres)
-- Run this in Supabase SQL Editor.

create extension if not exists "pgcrypto";

do $$
begin
  if not exists (select 1 from pg_type where typname = 'app_role') then
    create type app_role as enum ('admin', 'instructor', 'student');
  end if;
end $$;

create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text not null unique,
  full_name text,
  role app_role not null default 'student',
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);

create table if not exists public.categories (
  id bigserial primary key,
  name text not null unique,
  created_at timestamptz not null default now()
);

create table if not exists public.courses (
  id bigserial primary key,
  instructor_id uuid not null references public.profiles(id),
  instructor_name text not null,
  category_id bigint references public.categories(id),
  category_name text,
  title text not null,
  slug text not null unique,
  short_description text not null,
  description text not null,
  thumbnail_url text,
  price numeric(10,2) not null default 0,
  level text not null default 'beginner' check (level in ('beginner','intermediate','advanced')),
  status text not null default 'draft' check (status in ('draft','pending','approved','rejected')),
  rejection_reason text,
  review_avg numeric(4,2) not null default 0,
  review_count integer not null default 0,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.course_sections (
  id bigserial primary key,
  course_id bigint not null references public.courses(id) on delete cascade,
  title text not null,
  sort_order integer not null default 1,
  created_at timestamptz not null default now()
);

create table if not exists public.course_lessons (
  id bigserial primary key,
  section_id bigint not null references public.course_sections(id) on delete cascade,
  title text not null,
  content_type text not null default 'video' check (content_type in ('video','text')),
  video_url text,
  body_text text,
  duration_seconds integer not null default 0,
  sort_order integer not null default 1,
  is_preview boolean not null default false,
  created_at timestamptz not null default now()
);

create table if not exists public.enrollments (
  id bigserial primary key,
  student_id uuid not null references public.profiles(id) on delete cascade,
  course_id bigint not null references public.courses(id) on delete cascade,
  payment_provider text not null default 'mock' check (payment_provider in ('mock','stripe','razorpay')),
  payment_status text not null default 'success' check (payment_status in ('pending','success','failed')),
  payment_ref text,
  enrolled_at timestamptz not null default now(),
  unique(student_id, course_id)
);

create table if not exists public.lesson_progress (
  id bigserial primary key,
  student_id uuid not null references public.profiles(id) on delete cascade,
  lesson_id bigint not null references public.course_lessons(id) on delete cascade,
  completed boolean not null default true,
  completed_at timestamptz not null default now(),
  unique(student_id, lesson_id)
);

create table if not exists public.reviews (
  id bigserial primary key,
  student_id uuid not null references public.profiles(id) on delete cascade,
  student_name text not null default 'Student',
  course_id bigint not null references public.courses(id) on delete cascade,
  rating smallint not null check (rating between 1 and 5),
  comment text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique(student_id, course_id)
);

create table if not exists public.payments (
  id bigserial primary key,
  student_id uuid not null references public.profiles(id) on delete cascade,
  course_id bigint not null references public.courses(id) on delete cascade,
  provider text not null default 'mock' check (provider in ('mock','stripe','razorpay')),
  amount numeric(10,2) not null,
  currency text not null default 'USD',
  status text not null default 'success' check (status in ('pending','success','failed')),
  provider_ref text,
  created_at timestamptz not null default now()
);

create table if not exists public.course_approval_logs (
  id bigserial primary key,
  course_id bigint not null references public.courses(id) on delete cascade,
  admin_id uuid not null references public.profiles(id) on delete cascade,
  action text not null check (action in ('approved', 'rejected')),
  note text,
  created_at timestamptz not null default now()
);

create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
  role_value text;
begin
  role_value := coalesce(new.raw_user_meta_data ->> 'role', 'student');
  insert into public.profiles (id, email, full_name, role)
  values (
    new.id,
    coalesce(new.email, ''),
    new.raw_user_meta_data ->> 'full_name',
    case when role_value in ('admin','instructor','student') then role_value::app_role else 'student'::app_role end
  )
  on conflict (id) do update
    set email = excluded.email,
        full_name = coalesce(excluded.full_name, public.profiles.full_name);
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists set_courses_updated_at on public.courses;
create trigger set_courses_updated_at
  before update on public.courses
  for each row execute function public.set_updated_at();

drop trigger if exists set_reviews_updated_at on public.reviews;
create trigger set_reviews_updated_at
  before update on public.reviews
  for each row execute function public.set_updated_at();

create or replace function public.refresh_course_reviews()
returns trigger
language plpgsql
as $$
declare
  target_course_id bigint;
begin
  target_course_id := coalesce(new.course_id, old.course_id);
  update public.courses c
     set review_avg = coalesce((select avg(r.rating)::numeric(4,2) from public.reviews r where r.course_id = target_course_id), 0),
         review_count = (select count(*) from public.reviews r where r.course_id = target_course_id)
   where c.id = target_course_id;
  return null;
end;
$$;

drop trigger if exists reviews_after_insert on public.reviews;
create trigger reviews_after_insert
  after insert on public.reviews
  for each row execute function public.refresh_course_reviews();

drop trigger if exists reviews_after_update on public.reviews;
create trigger reviews_after_update
  after update on public.reviews
  for each row execute function public.refresh_course_reviews();

drop trigger if exists reviews_after_delete on public.reviews;
create trigger reviews_after_delete
  after delete on public.reviews
  for each row execute function public.refresh_course_reviews();

create or replace view public.course_lessons_view as
select
  c.id as course_id,
  cs.id as section_id,
  cs.title as section_title,
  cs.sort_order as section_order,
  cl.id as lesson_id,
  cl.title as lesson_title,
  cl.content_type,
  cl.video_url,
  cl.body_text,
  cl.sort_order as lesson_order,
  cl.is_preview
from public.courses c
join public.course_sections cs on cs.course_id = c.id
join public.course_lessons cl on cl.section_id = cs.id;

create or replace view public.student_course_progress as
select
  e.student_id,
  c.id as course_id,
  c.slug,
  c.title,
  c.level,
  coalesce(100.0 * count(lp.lesson_id) filter (where lp.completed) / nullif(count(cl.id), 0), 0)::numeric(5,2) as progress_pct,
  max(lp.completed_at) as last_activity
from public.enrollments e
join public.courses c on c.id = e.course_id
left join public.course_sections cs on cs.course_id = c.id
left join public.course_lessons cl on cl.section_id = cs.id
left join public.lesson_progress lp on lp.lesson_id = cl.id and lp.student_id = e.student_id
group by e.student_id, c.id;

create or replace view public.instructor_course_stats as
select
  c.instructor_id,
  c.id as course_id,
  c.title,
  c.status,
  c.price,
  c.created_at,
  count(e.id) as enrollments
from public.courses c
left join public.enrollments e on e.course_id = c.id
group by c.id;

insert into public.categories (name)
values ('Web Development'), ('Data Science'), ('Design'), ('Marketing')
on conflict (name) do nothing;

alter table public.profiles enable row level security;
alter table public.categories enable row level security;
alter table public.courses enable row level security;
alter table public.course_sections enable row level security;
alter table public.course_lessons enable row level security;
alter table public.enrollments enable row level security;
alter table public.lesson_progress enable row level security;
alter table public.reviews enable row level security;
alter table public.payments enable row level security;
alter table public.course_approval_logs enable row level security;

-- Profiles
drop policy if exists "Profiles are viewable by everyone" on public.profiles;
create policy "Profiles are viewable by everyone" on public.profiles for select using (true);

drop policy if exists "Users can update own profile" on public.profiles;
create policy "Users can update own profile" on public.profiles for update using (auth.uid() = id);

drop policy if exists "Users can insert own profile" on public.profiles;
create policy "Users can insert own profile" on public.profiles for insert with check (auth.uid() = id);

-- Categories
drop policy if exists "Categories are readable by everyone" on public.categories;
create policy "Categories are readable by everyone" on public.categories for select using (true);

drop policy if exists "Admins manage categories" on public.categories;
create policy "Admins manage categories" on public.categories
for all
using (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'))
with check (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'));

-- Courses
drop policy if exists "Approved courses visible to everyone" on public.courses;
create policy "Approved courses visible to everyone" on public.courses
for select using (status = 'approved' or auth.uid() = instructor_id or exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'));

drop policy if exists "Instructors manage own courses" on public.courses;
create policy "Instructors manage own courses" on public.courses
for all
using (auth.uid() = instructor_id)
with check (auth.uid() = instructor_id);

drop policy if exists "Admins manage all courses" on public.courses;
create policy "Admins manage all courses" on public.courses
for all
using (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'))
with check (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'));

-- Sections/Lessons
drop policy if exists "Sections readable for approved courses" on public.course_sections;
create policy "Sections readable for approved courses" on public.course_sections
for select
using (
  exists (
    select 1 from public.courses c
    where c.id = course_id
      and (c.status = 'approved' or c.instructor_id = auth.uid() or exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'))
  )
);

drop policy if exists "Instructors manage own sections" on public.course_sections;
create policy "Instructors manage own sections" on public.course_sections
for all
using (exists (select 1 from public.courses c where c.id = course_id and c.instructor_id = auth.uid()))
with check (exists (select 1 from public.courses c where c.id = course_id and c.instructor_id = auth.uid()));

drop policy if exists "Lessons readable for approved courses" on public.course_lessons;
create policy "Lessons readable for approved courses" on public.course_lessons
for select
using (
  exists (
    select 1
    from public.course_sections cs
    join public.courses c on c.id = cs.course_id
    where cs.id = section_id
      and (c.status = 'approved' or c.instructor_id = auth.uid() or exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'))
  )
);

drop policy if exists "Instructors manage own lessons" on public.course_lessons;
create policy "Instructors manage own lessons" on public.course_lessons
for all
using (
  exists (
    select 1 from public.course_sections cs
    join public.courses c on c.id = cs.course_id
    where cs.id = section_id and c.instructor_id = auth.uid()
  )
)
with check (
  exists (
    select 1 from public.course_sections cs
    join public.courses c on c.id = cs.course_id
    where cs.id = section_id and c.instructor_id = auth.uid()
  )
);

-- Enrollments
drop policy if exists "Students read own enrollments" on public.enrollments;
create policy "Students read own enrollments" on public.enrollments for select using (auth.uid() = student_id);

drop policy if exists "Students create own enrollments" on public.enrollments;
create policy "Students create own enrollments" on public.enrollments for insert with check (auth.uid() = student_id);

-- Lesson progress
drop policy if exists "Students manage own progress" on public.lesson_progress;
create policy "Students manage own progress" on public.lesson_progress
for all
using (auth.uid() = student_id)
with check (auth.uid() = student_id);

-- Reviews
drop policy if exists "Reviews readable by everyone" on public.reviews;
create policy "Reviews readable by everyone" on public.reviews for select using (true);

drop policy if exists "Students manage own reviews if enrolled" on public.reviews;
create policy "Students manage own reviews if enrolled" on public.reviews
for all
using (
  auth.uid() = student_id and exists (
    select 1 from public.enrollments e
    where e.student_id = auth.uid() and e.course_id = reviews.course_id
  )
)
with check (
  auth.uid() = student_id and exists (
    select 1 from public.enrollments e
    where e.student_id = auth.uid() and e.course_id = reviews.course_id
  )
);

-- Payments
drop policy if exists "Students read own payments" on public.payments;
create policy "Students read own payments" on public.payments for select using (auth.uid() = student_id);

drop policy if exists "Students create own payments" on public.payments;
create policy "Students create own payments" on public.payments for insert with check (auth.uid() = student_id);

-- Approval logs
drop policy if exists "Admins manage approval logs" on public.course_approval_logs;
create policy "Admins manage approval logs" on public.course_approval_logs
for all
using (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'))
with check (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'));

-- View access (uses underlying table policies)
grant select on public.course_lessons_view to anon, authenticated;
grant select on public.student_course_progress to authenticated;
grant select on public.instructor_course_stats to authenticated;

