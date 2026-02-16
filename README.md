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
