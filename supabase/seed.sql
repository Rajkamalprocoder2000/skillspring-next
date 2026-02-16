-- Optional seed helpers
-- Replace emails with your actual accounts from Supabase Auth.

update public.profiles
set role = 'admin'
where email = 'admin@skillspring.test';

update public.profiles
set role = 'instructor'
where email = 'instructor@skillspring.test';

update public.profiles
set role = 'student'
where email = 'student@skillspring.test';

