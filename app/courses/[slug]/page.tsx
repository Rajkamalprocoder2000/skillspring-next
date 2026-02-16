import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import Link from "next/link";
import { createServerSupabaseClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth";
import { requireActionProfile } from "@/lib/server-auth";

type Params = Promise<{ slug: string }>;

export default async function CourseDetailsPage({ params }: { params: Params }) {
  const { slug } = await params;
  const supabase = await createServerSupabaseClient();

  const {
    data: { user },
  } = await supabase.auth.getUser();
  const profile = await getCurrentProfile(supabase);

  const { data: course } = await supabase
    .from("courses")
    .select(
      "id, slug, title, description, price, level, category_name, instructor_name, review_avg, review_count, status",
    )
    .eq("slug", slug)
    .eq("status", "approved")
    .single();

  if (!course) {
    return <p>Course not found.</p>;
  }
  const courseId = course.id;

  const { data: curriculum } = await supabase
    .from("course_lessons_view")
    .select("section_id, section_title, lesson_id, lesson_title, is_preview, lesson_order, section_order")
    .eq("course_id", course.id)
    .order("section_order", { ascending: true })
    .order("lesson_order", { ascending: true });

  const { data: reviews } = await supabase
    .from("reviews")
    .select("rating, comment, created_at, student_name")
    .eq("course_id", course.id)
    .order("created_at", { ascending: false })
    .limit(10);

  const { data: enrollment } = user
    ? await supabase.from("enrollments").select("id").eq("course_id", course.id).eq("student_id", user.id).maybeSingle()
    : { data: null };

  async function enrollAction() {
    "use server";
    const { supabase: server, user: currentUser } = await requireActionProfile(["student"]);

    const { data: existing } = await server
      .from("enrollments")
      .select("id")
      .eq("student_id", currentUser.id)
      .eq("course_id", courseId)
      .maybeSingle();

    if (!existing) {
      await server.from("enrollments").insert({
        student_id: currentUser.id,
        course_id: courseId,
        payment_provider: "mock",
        payment_status: "success",
        payment_ref: null,
      });
    }

    revalidatePath(`/courses/${slug}`);
    redirect(`/student/player/${courseId}`);
  }

  async function reviewAction(formData: FormData) {
    "use server";
    const rating = Number(formData.get("rating") ?? 0);
    const comment = String(formData.get("comment") ?? "").trim();

    const { supabase: server, user: currentUser } = await requireActionProfile(["student"]);

    const { data: allowed } = await server
      .from("enrollments")
      .select("id")
      .eq("student_id", currentUser.id)
      .eq("course_id", courseId)
      .maybeSingle();

    if (!allowed) redirect(`/courses/${slug}`);

    const { data: reviewer } = await server.from("profiles").select("full_name").eq("id", currentUser.id).single();

    await server.from("reviews").upsert(
      {
        course_id: courseId,
        student_id: currentUser.id,
        student_name: reviewer?.full_name ?? "Student",
        rating: Math.max(1, Math.min(5, rating)),
        comment,
      },
      { onConflict: "course_id,student_id" },
    );

    revalidatePath(`/courses/${slug}`);
  }

  const grouped = new Map<number, { title: string; lessons: { id: number; title: string; preview: boolean }[] }>();
  (curriculum ?? []).forEach((item) => {
    if (!grouped.has(item.section_id)) {
      grouped.set(item.section_id, { title: item.section_title, lessons: [] });
    }
    grouped.get(item.section_id)?.lessons.push({
      id: item.lesson_id,
      title: item.lesson_title,
      preview: Boolean(item.is_preview),
    });
  });

  return (
    <div style={{ paddingBottom: 24 }}>
      <article className="card">
        <span className="pill">{course.category_name ?? "General"}</span>
        <h1>{course.title}</h1>
        <p className="muted">
          By {course.instructor_name} | {course.level}
        </p>
        <p className="muted">
          Rating: {Number(course.review_avg ?? 0).toFixed(1)} ({course.review_count ?? 0})
        </p>
        <p>{course.description}</p>
        <p>
          <strong>${Number(course.price ?? 0).toFixed(2)}</strong>
        </p>

        {!profile && (
          <Link href="/auth/login" className="btn">
            Login to enroll
          </Link>
        )}
        {profile?.role === "student" && !enrollment && (
          <form action={enrollAction}>
            <button className="btn" type="submit">
              Enroll with Mock Payment
            </button>
          </form>
        )}
        {profile?.role === "student" && enrollment && (
          <Link href={`/student/player/${course.id}`} className="btn success">
            Continue Course
          </Link>
        )}
      </article>

      <div className="split" style={{ marginTop: 14 }}>
        <section className="card">
          <h2>Curriculum</h2>
          {[...grouped.entries()].map(([sectionId, section]) => (
            <div key={sectionId}>
              <h3>{section.title}</h3>
              <ul>
                {section.lessons.map((lesson) => (
                  <li key={lesson.id}>
                    {lesson.title} {lesson.preview ? "(preview)" : ""}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </section>

        <section className="card">
          <h2>Reviews</h2>
          {(reviews ?? []).map((review, index) => (
            <article key={`${review.created_at}-${index}`} style={{ marginBottom: 10 }}>
              <strong>{review.student_name}</strong> - {review.rating}/5
              <p style={{ margin: "4px 0" }}>{review.comment}</p>
            </article>
          ))}
          {profile?.role === "student" && enrollment && (
            <form action={reviewAction}>
              <label>Rate this course</label>
              <input name="rating" type="number" min={1} max={5} defaultValue={5} required />
              <label>Comment</label>
              <textarea name="comment" />
              <button className="btn secondary" type="submit">
                Submit Review
              </button>
            </form>
          )}
        </section>
      </div>
    </div>
  );
}
