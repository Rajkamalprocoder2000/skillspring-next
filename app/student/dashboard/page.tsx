import Link from "next/link";
import { requireProfile } from "@/lib/auth";

export default async function StudentDashboardPage() {
  const { supabase, profile } = await requireProfile(["student"]);

  const { data: enrollments } = await supabase
    .from("student_course_progress")
    .select("course_id, slug, title, level, progress_pct")
    .eq("student_id", profile.id)
    .order("last_activity", { ascending: false });

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Student Dashboard</h1>
      <p className="muted">Track your enrolled courses and progress.</p>
      <div className="grid">
        {(enrollments ?? []).map((item) => (
          <article className="card" key={item.course_id}>
            <h3>{item.title}</h3>
            <p className="muted">Level: {item.level}</p>
            <p className="muted">Progress: {Number(item.progress_pct ?? 0).toFixed(1)}%</p>
            <Link className="btn" href={`/student/player/${item.course_id}`}>
              Continue
            </Link>
            <Link className="btn secondary" href={`/courses/${item.slug}`} style={{ marginLeft: 8 }}>
              Course page
            </Link>
          </article>
        ))}
      </div>
    </div>
  );
}

