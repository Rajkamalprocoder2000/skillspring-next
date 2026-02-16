import Link from "next/link";
import { requireProfile } from "@/lib/auth";

export default async function InstructorDashboardPage() {
  const { supabase, profile } = await requireProfile(["instructor"]);

  const { data: courses } = await supabase
    .from("instructor_course_stats")
    .select("course_id, title, status, price, enrollments")
    .eq("instructor_id", profile.id)
    .order("created_at", { ascending: false });

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Instructor Dashboard</h1>
      <p className="muted">Manage your courses and curriculum.</p>
      <p>
        <Link href="/instructor/builder" className="btn">
          Open Course Builder
        </Link>
      </p>
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Status</th>
            <th>Price</th>
            <th>Enrollments</th>
          </tr>
        </thead>
        <tbody>
          {(courses ?? []).map((course) => (
            <tr key={course.course_id}>
              <td>{course.title}</td>
              <td>{course.status}</td>
              <td>${Number(course.price ?? 0).toFixed(2)}</td>
              <td>{course.enrollments ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

