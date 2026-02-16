import Link from "next/link";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export default async function HomePage() {
  let courses: Array<{
    id: number;
    slug: string;
    title: string;
    short_description: string | null;
    price: number | null;
    level: string;
    review_avg: number | null;
    review_count: number | null;
  }> = [];
  let setupError = false;

  try {
    const supabase = await createServerSupabaseClient();
    const { data } = await supabase
      .from("courses")
      .select("id, slug, title, short_description, price, level, review_avg, review_count")
      .eq("status", "approved")
      .order("review_count", { ascending: false })
      .order("created_at", { ascending: false })
      .limit(6);
    courses = data ?? [];
  } catch {
    setupError = true;
  }

  return (
    <div style={{ paddingBottom: 24 }}>
      <section className="hero">
        <h1 style={{ fontSize: 38, margin: "0 0 8px" }}>Learn practical skills with SkillSpring</h1>
        <p style={{ marginTop: 0, marginBottom: 18, color: "#cbd5e1" }}>
          Instructor-led courses, structured lessons, progress tracking, and role-based learning management.
        </p>
        <Link className="btn" href="/courses">
          Explore Marketplace
        </Link>
        <Link className="btn secondary" href="/setup" style={{ marginLeft: 8 }}>
          Run Setup Check
        </Link>
      </section>

      <h2 style={{ marginTop: 22 }}>Trending Courses</h2>
      {setupError && (
        <div className="card" style={{ marginBottom: 12 }}>
          <p className="muted">
            Setup pending: configure Supabase env vars and run `supabase/schema.sql` to enable full functionality.
          </p>
        </div>
      )}
      <div className="grid">
        {courses.map((course) => (
          <article className="card" key={course.id}>
            <span className="pill">{course.level}</span>
            <h3>{course.title}</h3>
            <p className="muted">{course.short_description ?? "No description yet."}</p>
            <p className="muted">
              Rating: {Number(course.review_avg ?? 0).toFixed(1)} ({course.review_count ?? 0})
            </p>
            <p>
              <strong>${Number(course.price ?? 0).toFixed(2)}</strong>
            </p>
            <Link className="btn secondary" href={`/courses/${course.slug}`}>
              View Course
            </Link>
          </article>
        ))}
      </div>
    </div>
  );
}
