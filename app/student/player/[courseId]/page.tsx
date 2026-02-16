import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireProfile } from "@/lib/auth";
import { requireActionProfile } from "@/lib/server-auth";

type Params = Promise<{ courseId: string }>;
type SearchParams = Promise<{ lesson?: string }>;

export default async function CoursePlayerPage({
  params,
  searchParams,
}: {
  params: Params;
  searchParams: SearchParams;
}) {
  const { supabase, profile } = await requireProfile(["student"]);
  const { courseId } = await params;
  const sp = await searchParams;
  const parsedCourseId = Number(courseId);

  const { data: enrollment } = await supabase
    .from("enrollments")
    .select("id")
    .eq("student_id", profile.id)
    .eq("course_id", parsedCourseId)
    .maybeSingle();

  if (!enrollment) {
    redirect("/student/dashboard");
  }

  const { data: course } = await supabase.from("courses").select("id, title").eq("id", parsedCourseId).single();
  const { data: lessons } = await supabase
    .from("course_lessons_view")
    .select("lesson_id, lesson_title, section_title, content_type, video_url, body_text")
    .eq("course_id", parsedCourseId)
    .order("section_order", { ascending: true })
    .order("lesson_order", { ascending: true });

  if (!course || !lessons || lessons.length === 0) {
    return <p>No lessons available for this course.</p>;
  }

  const activeLessonId = Number(sp.lesson ?? lessons[0].lesson_id);
  const activeLesson = lessons.find((l) => l.lesson_id === activeLessonId) ?? lessons[0];

  const { data: progressRows } = await supabase
    .from("lesson_progress")
    .select("lesson_id")
    .eq("student_id", profile.id)
    .in(
      "lesson_id",
      lessons.map((l) => l.lesson_id),
    );

  const doneIds = new Set((progressRows ?? []).map((r) => r.lesson_id));

  async function markCompleteAction(formData: FormData) {
    "use server";
    const lessonId = Number(formData.get("lesson_id") ?? 0);
    const id = Number(formData.get("course_id") ?? 0);
    const { supabase: server, user } = await requireActionProfile(["student"]);
    if (!lessonId || !id) redirect("/student/dashboard");

    const { data: allowedEnrollment } = await server
      .from("enrollments")
      .select("id")
      .eq("student_id", user.id)
      .eq("course_id", id)
      .maybeSingle();

    if (!allowedEnrollment) {
      redirect("/student/dashboard");
    }

    const { data: allowedLesson } = await server
      .from("course_lessons_view")
      .select("lesson_id")
      .eq("course_id", id)
      .eq("lesson_id", lessonId)
      .maybeSingle();

    if (!allowedLesson) {
      redirect(`/student/player/${id}`);
    }

    await server.from("lesson_progress").upsert(
      {
        student_id: user.id,
        lesson_id: lessonId,
        completed: true,
      },
      { onConflict: "student_id,lesson_id" },
    );

    revalidatePath(`/student/player/${id}`);
    redirect(`/student/player/${id}?lesson=${lessonId}`);
  }

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>{course.title}</h1>
      <div className="split">
        <section className="card">
          <h2>{activeLesson.lesson_title}</h2>
          {activeLesson.content_type === "video" && activeLesson.video_url ? (
            <iframe
              src={activeLesson.video_url}
              style={{ width: "100%", minHeight: 380, border: 0, borderRadius: 8 }}
              allowFullScreen
            />
          ) : (
            <article>{activeLesson.body_text ?? "No lesson body yet."}</article>
          )}
          <form action={markCompleteAction}>
            <input type="hidden" name="lesson_id" value={activeLesson.lesson_id} />
            <input type="hidden" name="course_id" value={course.id} />
            <button className="btn success" type="submit">
              Mark Completed
            </button>
          </form>
        </section>

        <aside className="card">
          <h3>Lessons</h3>
          <ul>
            {lessons.map((lesson) => (
              <li key={lesson.lesson_id}>
                <a href={`/student/player/${course.id}?lesson=${lesson.lesson_id}`}>
                  {lesson.lesson_title} {doneIds.has(lesson.lesson_id) ? "(done)" : ""}
                </a>
              </li>
            ))}
          </ul>
        </aside>
      </div>
    </div>
  );
}
