# SkillSpring (Next.js + Supabase)

Udemy-style learning platform with role-based portals:
- Admin
- Instructor
- Student

## Implemented

- Supabase email/password auth
- Role-based access via `profiles.role`
- Public marketplace:
  - Home: `app/page.tsx`
  - Listings: `app/courses/page.tsx`
  - Details: `app/courses/[slug]/page.tsx`
- Student:
  - Dashboard: `app/student/dashboard/page.tsx`
  - Course player + progress: `app/student/player/[courseId]/page.tsx`
  - Reviews (enrollment-gated)
- Instructor:
  - Dashboard: `app/instructor/dashboard/page.tsx`
  - Course builder: `app/instructor/builder/page.tsx`
- Admin:
  - Dashboard + approvals + user/category management: `app/admin/dashboard/page.tsx`

## Supabase Setup

1. Create a Supabase project.
2. In SQL Editor, run:
   - `supabase/schema.sql`
3. Open app route `/setup` and verify all checks are green.
4. In Authentication settings:
   - Disable email confirmation for local testing (or confirm signup emails manually).

## Environment

Create `.env.local`:

```env
NEXT_PUBLIC_SUPABASE_URL=your_supabase_project_url
NEXT_PUBLIC_SUPABASE_ANON_KEY=your_supabase_anon_key
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

## Run

```bash
npm install
npm run dev
```

Open:
- `http://localhost:3000`

## Setup Verification

After running SQL and env setup, open:
- `http://localhost:3000/setup`

All checks should be successful before functional testing.

## Role Notes

- Signup allows `student` and `instructor`.
- Set an admin by updating `profiles.role = 'admin'` in Supabase (see `supabase/seed.sql`).
- There is no default password seeded by SQL.

## Test Accounts (Create Manually)

Create these via signup UI, then apply roles:

```sql
update public.profiles set role = 'admin' where email = 'admin@skillspring.test';
update public.profiles set role = 'instructor' where email = 'instructor@skillspring.test';
update public.profiles set role = 'student' where email = 'student@skillspring.test';
```

Use the same passwords entered at signup.

## Payment Notes

- Enrollment currently uses mock payment logic in app actions.
- Stripe/Razorpay can be added by replacing enroll action with checkout + webhook flow.

## Deploy PHP App (GitHub + Vercel)

This repository contains both Next.js and PHP. For PHP deployment on Vercel, this repo uses `vercel.json` with `vercel-php` runtime.

### 1. GitHub

```bash
git add .
git commit -m "chore: prepare PHP deployment for GitHub and Vercel"
git remote add origin https://github.com/<your-username>/<your-repo>.git
git branch -M main
git push -u origin main
```

### 2. Vercel Project

- Import the same GitHub repository in Vercel.
- Framework Preset: `Other`
- Root Directory: `./`

### 3. Vercel Environment Variables

Set these in Vercel Project Settings -> Environment Variables:

- `DB_HOST` (or use `DATABASE_URL` instead)
- `DB_PORT` (default `5432`)
- `DB_NAME` (default `postgres`)
- `DB_USER`
- `DB_PASS`
- `DB_SSLMODE` (default `require`)
- `APP_BASE_PATH` (set empty for production)

### 4. Notes

- `/` routes to `index.php` via `vercel.json`.
- Keep `.php` URLs for internal pages (`/login.php`, `/courses.php`, etc.).
- Static assets are served from `assets/`.

## PHP + Supabase (Postgres)

For the PHP app, run this SQL in Supabase SQL editor:

- `supabase/schema_php.sql`

This is separate from `supabase/schema.sql` used by the Next.js app and Supabase Auth flow.
