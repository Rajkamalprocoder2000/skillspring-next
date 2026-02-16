import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireProfile } from "@/lib/auth";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export default async function AdminDashboardPage() {
  const { supabase, profile } = await requireProfile(["admin"]);

  const [{ count: users }, { count: courses }, { count: pending }, { count: enrollments }] = await Promise.all([
    supabase.from("profiles").select("*", { count: "exact", head: true }),
    supabase.from("courses").select("*", { count: "exact", head: true }),
    supabase.from("courses").select("*", { count: "exact", head: true }).eq("status", "pending"),
    supabase.from("enrollments").select("*", { count: "exact", head: true }),
  ]);

  const { data: pendingCourses } = await supabase
    .from("courses")
    .select("id, title, instructor_name, price, status")
    .eq("status", "pending")
    .order("created_at", { ascending: true });

  const { data: userList } = await supabase
    .from("profiles")
    .select("id, full_name, role, is_active, email")
    .order("created_at", { ascending: false })
    .limit(100);

  const { data: categories } = await supabase.from("categories").select("id, name").order("name");

  async function approveAction(formData: FormData) {
    "use server";
    const courseId = Number(formData.get("course_id") ?? 0);
    const note = String(formData.get("note") ?? "").trim();
    const server = await createServerSupabaseClient();
    const {
      data: { user },
    } = await server.auth.getUser();
    if (!user || !courseId) redirect("/auth/login");

    await server
      .from("courses")
      .update({ status: "approved", rejection_reason: null, published_at: new Date().toISOString() })
      .eq("id", courseId);

    await server.from("course_approval_logs").insert({
      course_id: courseId,
      admin_id: user.id,
      action: "approved",
      note,
    });

    revalidatePath("/admin/dashboard");
    redirect("/admin/dashboard");
  }

  async function rejectAction(formData: FormData) {
    "use server";
    const courseId = Number(formData.get("course_id") ?? 0);
    const reason = String(formData.get("reason") ?? "").trim() || "Rejected by admin";
    const server = await createServerSupabaseClient();
    const {
      data: { user },
    } = await server.auth.getUser();
    if (!user || !courseId) redirect("/auth/login");

    await server.from("courses").update({ status: "rejected", rejection_reason: reason }).eq("id", courseId);

    await server.from("course_approval_logs").insert({
      course_id: courseId,
      admin_id: user.id,
      action: "rejected",
      note: reason,
    });

    revalidatePath("/admin/dashboard");
    redirect("/admin/dashboard");
  }

  async function toggleUserAction(formData: FormData) {
    "use server";
    const userId = String(formData.get("user_id") ?? "");
    const isActive = String(formData.get("is_active") ?? "") === "true";
    if (!userId) return;
    const server = await createServerSupabaseClient();
    await server.from("profiles").update({ is_active: isActive }).eq("id", userId);
    revalidatePath("/admin/dashboard");
    redirect("/admin/dashboard");
  }

  async function addCategoryAction(formData: FormData) {
    "use server";
    const name = String(formData.get("name") ?? "").trim();
    if (!name) return;
    const server = await createServerSupabaseClient();
    await server.from("categories").upsert({ name }, { onConflict: "name" });
    revalidatePath("/admin/dashboard");
    redirect("/admin/dashboard");
  }

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Admin Dashboard</h1>
      <p className="muted">Welcome, {profile.full_name ?? "Admin"}.</p>

      <div className="grid">
        <article className="card">
          <h3>Users</h3>
          <p>{users ?? 0}</p>
        </article>
        <article className="card">
          <h3>Courses</h3>
          <p>{courses ?? 0}</p>
        </article>
        <article className="card">
          <h3>Pending Approvals</h3>
          <p>{pending ?? 0}</p>
        </article>
        <article className="card">
          <h3>Enrollments</h3>
          <p>{enrollments ?? 0}</p>
        </article>
      </div>

      <section className="card" style={{ marginTop: 14 }}>
        <h2>Course Approvals</h2>
        {(pendingCourses ?? []).map((course) => (
          <article className="card" key={course.id} style={{ marginBottom: 10 }}>
            <h3>{course.title}</h3>
            <p className="muted">
              By {course.instructor_name} | ${Number(course.price ?? 0).toFixed(2)}
            </p>
            <form action={approveAction}>
              <input type="hidden" name="course_id" value={course.id} />
              <input name="note" placeholder="Approval note (optional)" />
              <button className="btn success" type="submit">
                Approve
              </button>
            </form>
            <form action={rejectAction}>
              <input type="hidden" name="course_id" value={course.id} />
              <input name="reason" placeholder="Reason for rejection" required />
              <button className="btn danger" type="submit">
                Reject
              </button>
            </form>
          </article>
        ))}
      </section>

      <section className="card" style={{ marginTop: 14 }}>
        <h2>User Management</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {(userList ?? []).map((user) => (
              <tr key={user.id}>
                <td>{user.full_name}</td>
                <td>{user.email}</td>
                <td>{user.role}</td>
                <td>{user.is_active ? "Active" : "Disabled"}</td>
                <td>
                  <form action={toggleUserAction}>
                    <input type="hidden" name="user_id" value={user.id} />
                    <input type="hidden" name="is_active" value={String(!user.is_active)} />
                    <button className="btn secondary" type="submit">
                      {user.is_active ? "Disable" : "Enable"}
                    </button>
                  </form>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <section className="card" style={{ marginTop: 14 }}>
        <h2>Categories</h2>
        <form action={addCategoryAction}>
          <label>New category</label>
          <input name="name" required />
          <button className="btn" type="submit">
            Save
          </button>
        </form>
        <p className="muted">
          {(categories ?? [])
            .map((c) => c.name)
            .join(", ")}
        </p>
      </section>
    </div>
  );
}

