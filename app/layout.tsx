import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";
import { createServerSupabaseClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth";

export const metadata: Metadata = {
  title: "SkillSpring",
  description: "Role-based online learning platform built with Next.js + Supabase",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  let user: { id: string } | null = null;
  let profile: Awaited<ReturnType<typeof getCurrentProfile>> = null;

  try {
    const supabase = await createServerSupabaseClient();
    const {
      data: { user: currentUser },
    } = await supabase.auth.getUser();
    user = currentUser ? { id: currentUser.id } : null;
    profile = currentUser ? await getCurrentProfile(supabase) : null;
  } catch {
    user = null;
    profile = null;
  }

  return (
    <html lang="en">
      <body>
        <header className="topbar">
          <div className="container nav">
            <Link href="/" className="brand">
              SkillSpring
            </Link>
            <nav className="menu">
              <Link href="/courses">Courses</Link>
              {!user && <Link href="/auth/login">Login</Link>}
              {!user && <Link href="/auth/signup">Signup</Link>}
              {profile?.role === "student" && <Link href="/student/dashboard">Student</Link>}
              {profile?.role === "instructor" && <Link href="/instructor/dashboard">Instructor</Link>}
              {profile?.role === "admin" && <Link href="/admin/dashboard">Admin</Link>}
              {user && <Link href="/logout">Logout</Link>}
            </nav>
          </div>
        </header>
        <main className="container">{children}</main>
      </body>
    </html>
  );
}
