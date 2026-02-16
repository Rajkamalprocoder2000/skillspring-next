import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireProfile } from "@/lib/auth";
import { requireActionProfile } from "@/lib/server-auth";

type SearchParams = Promise<{ course?: string }>;

export default async function CourseBuilderPage({ searchParams }: { searchParams: SearchParams }) {
  const { supabase, profile } = await requireProfile(["instructor"]);
  const sp = await searchParams;
  const activeCourseId = Number(sp.course ?? 0);

  const { data: categories } = await supabase.from("categories").select("id, name").order("name");
  const { data: myCourses } = await supabase
    .from("courses")
    .select("id, title, status, rejection_reason")
    .eq("instructor_id", profile.id)
    .order("created_at", { ascending: false });

  const activeCourse = (myCourses ?? []).find((c) => c.id === activeCourseId) ?? null;

  const { data: sections } = activeCourse
    ? await supabase
        .from("course_sections")
        .select("id, title, sort_order")
        .eq("course_id", activeCourse.id)
        .order("sort_order", { ascending: true })
    : { data: [] };

  const { data: lessons } = activeCourse
    ? await supabase
        .from("course_lessons_view")
        .select("section_id, section_title, lesson_title")
        .eq("course_id", activeCourse.id)
        .order("section_order", { ascending: true })
        .order("lesson_order", { ascending: true })
    : { data: [] };

  async function createCourseAction(formData: FormData) {
    "use server";
    const { supabase: server, user } = await requireActionProfile(["instructor"]);

    const title = String(formData.get("title") ?? "").trim();
    const shortDescription = String(formData.get("short_description") ?? "").trim();
    const description = String(formData.get("description") ?? "").trim();
    const level = String(formData.get("level") ?? "beginner");
    const price = Number(formData.get("price") ?? 0);
    const categoryId = Number(formData.get("category_id") ?? 0);

    if (!title || !shortDescription || !description) return;

    const slug = `${title.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "")}-${Date.now()}`;

    const { data: profileData } = await server.from("profiles").select("full_name").eq("id", user.id).single();
    const { data: categoryData } = await server.from("categories").select("name").eq("id", categoryId).single();

    await server.from("courses").insert({
      instructor_id: user.id,
      instructor_name: profileData?.full_name ?? "Instructor",
      category_id: categoryId || null,
      category_name: categoryData?.name ?? "General",
      title,
      slug,
      short_description: shortDescription,
      description,
      level,
      price,
      status: "draft",
    });

    revalidatePath("/instructor/builder");
    redirect("/instructor/builder");
  }

  async function addSectionAction(formData: FormData) {
    "use server";
    const courseId = Number(formData.get("course_id") ?? 0);
    const title = String(formData.get("title") ?? "").trim();
    const sortOrder = Number(formData.get("sort_order") ?? 0);
    if (!courseId || !title) return;
    const { supabase: server, user } = await requireActionProfile(["instructor"]);
    const { data: course } = await server
      .from("courses")
      .select("id")
      .eq("id", courseId)
      .eq("instructor_id", user.id)
      .maybeSingle();
    if (!course) redirect("/instructor/builder");
    await server.from("course_sections").insert({ course_id: courseId, title, sort_order: sortOrder });
    revalidatePath(`/instructor/builder?course=${courseId}`);
    redirect(`/instructor/builder?course=${courseId}`);
  }

  async function addLessonAction(formData: FormData) {
    "use server";
    const courseId = Number(formData.get("course_id") ?? 0);
    const sectionId = Number(formData.get("section_id") ?? 0);
    const title = String(formData.get("title") ?? "").trim();
    const contentType = String(formData.get("content_type") ?? "video");
    const videoUrl = String(formData.get("video_url") ?? "").trim();
    const bodyText = String(formData.get("body_text") ?? "").trim();
    const durationSeconds = Number(formData.get("duration_seconds") ?? 0);
    const sortOrder = Number(formData.get("sort_order") ?? 0);
    const isPreview = formData.get("is_preview") === "on";

    if (!courseId || !sectionId || !title) return;

    const { supabase: server, user } = await requireActionProfile(["instructor"]);
    const { data: section } = await server
      .from("course_sections")
      .select("id, course_id, courses!inner(instructor_id)")
      .eq("id", sectionId)
      .eq("course_id", courseId)
      .eq("courses.instructor_id", user.id)
      .maybeSingle();

    if (!section) redirect("/instructor/builder");

    await server.from("course_lessons").insert({
      section_id: sectionId,
      title,
      content_type: contentType,
      video_url: videoUrl || null,
      body_text: bodyText || null,
      duration_seconds: durationSeconds,
      sort_order: sortOrder,
      is_preview: isPreview,
    });
    revalidatePath(`/instructor/builder?course=${courseId}`);
    redirect(`/instructor/builder?course=${courseId}`);
  }

  async function submitForApprovalAction(formData: FormData) {
    "use server";
    const courseId = Number(formData.get("course_id") ?? 0);
    if (!courseId) return;
    const { supabase: server, user } = await requireActionProfile(["instructor"]);
    await server
      .from("courses")
      .update({ status: "pending", rejection_reason: null })
      .eq("id", courseId)
      .eq("instructor_id", user.id);
    revalidatePath(`/instructor/builder?course=${courseId}`);
    redirect(`/instructor/builder?course=${courseId}`);
  }

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Course Builder</h1>
      <p className="muted">Create, structure, and submit your courses for review.</p>

      <section className="card">
        <h2>Create Course</h2>
        <form action={createCourseAction}>
          <label>Title</label>
          <input name="title" required />
          <label>Short Description</label>
          <input name="short_description" required />
          <label>Description</label>
          <textarea name="description" required />
          <div className="grid">
            <div>
              <label>Category</label>
              <select name="category_id" defaultValue="">
                <option value="">General</option>
                {(categories ?? []).map((cat) => (
                  <option key={cat.id} value={cat.id}>
                    {cat.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label>Level</label>
              <select name="level" defaultValue="beginner">
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
              </select>
            </div>
            <div>
              <label>Price</label>
              <input name="price" type="number" min={0} step="0.01" defaultValue={0} />
            </div>
          </div>
          <button className="btn" type="submit">
            Create Draft
          </button>
        </form>
      </section>

      <section className="card" style={{ marginTop: 14 }}>
        <h2>My Courses</h2>
        <ul>
          {(myCourses ?? []).map((course) => (
            <li key={course.id}>
              <a href={`/instructor/builder?course=${course.id}`}>
                {course.title} ({course.status})
              </a>
            </li>
          ))}
        </ul>
      </section>

      {activeCourse && (
        <>
          <section className="card" style={{ marginTop: 14 }}>
            <h2>Editing: {activeCourse.title}</h2>
            <p>
              Status: <strong>{activeCourse.status}</strong>
            </p>
            {activeCourse.rejection_reason && <p style={{ color: "#b91c1c" }}>{activeCourse.rejection_reason}</p>}
            <form action={submitForApprovalAction}>
              <input type="hidden" name="course_id" value={activeCourse.id} />
              <button className="btn success" type="submit">
                Submit for Approval
              </button>
            </form>
          </section>

          <section className="card" style={{ marginTop: 14 }}>
            <h2>Add Section</h2>
            <form action={addSectionAction}>
              <input type="hidden" name="course_id" value={activeCourse.id} />
              <label>Section Title</label>
              <input name="title" required />
              <label>Sort Order</label>
              <input name="sort_order" type="number" defaultValue={1} />
              <button className="btn" type="submit">
                Add Section
              </button>
            </form>
          </section>

          <section className="card" style={{ marginTop: 14 }}>
            <h2>Add Lesson</h2>
            <form action={addLessonAction}>
              <input type="hidden" name="course_id" value={activeCourse.id} />
              <label>Section</label>
              <select name="section_id" required>
                <option value="">Choose section</option>
                {(sections ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.title}
                  </option>
                ))}
              </select>
              <label>Lesson Title</label>
              <input name="title" required />
              <label>Content Type</label>
              <select name="content_type" defaultValue="video">
                <option value="video">Video</option>
                <option value="text">Text</option>
              </select>
              <label>Video URL (embed)</label>
              <input name="video_url" />
              <label>Body Text</label>
              <textarea name="body_text" />
              <div className="grid">
                <div>
                  <label>Duration (sec)</label>
                  <input name="duration_seconds" type="number" defaultValue={0} />
                </div>
                <div>
                  <label>Sort Order</label>
                  <input name="sort_order" type="number" defaultValue={1} />
                </div>
              </div>
              <label>
                <input name="is_preview" type="checkbox" /> Preview lesson
              </label>
              <button className="btn" type="submit">
                Add Lesson
              </button>
            </form>
          </section>

          <section className="card" style={{ marginTop: 14 }}>
            <h2>Curriculum Preview</h2>
            <ul>
              {(lessons ?? []).map((lesson, idx) => (
                <li key={`${lesson.section_id}-${idx}`}>
                  {lesson.section_title}: {lesson.lesson_title}
                </li>
              ))}
            </ul>
          </section>
        </>
      )}
    </div>
  );
}
