import Link from "next/link";
import { createServerSupabaseClient } from "@/lib/supabase/server";

type SearchParams = Promise<{
  q?: string;
  level?: string;
  category?: string;
}>;

export default async function CoursesPage({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const q = (params.q ?? "").trim();
  const level = (params.level ?? "").trim();
  const category = (params.category ?? "").trim();

  const supabase = await createServerSupabaseClient();
  let query = supabase
    .from("courses")
    .select("id, slug, title, short_description, price, level, category_name, review_avg, review_count")
    .eq("status", "approved")
    .order("created_at", { ascending: false });

  if (q) query = query.or(`title.ilike.%${q}%,short_description.ilike.%${q}%`);
  if (level) query = query.eq("level", level);
  if (category) query = query.eq("category_name", category);

  const { data: courses } = await query;
  const { data: categories } = await supabase.from("categories").select("name").order("name");

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Course Marketplace</h1>

      <form className="card" style={{ marginBottom: 16 }}>
        <label>Search</label>
        <input name="q" defaultValue={q} placeholder="Course title or keyword" />
        <div className="grid">
          <div>
            <label>Level</label>
            <select name="level" defaultValue={level}>
              <option value="">All</option>
              <option value="beginner">Beginner</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
          <div>
            <label>Category</label>
            <select name="category" defaultValue={category}>
              <option value="">All</option>
              {(categories ?? []).map((c) => (
                <option key={c.name} value={c.name}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
        </div>
        <button className="btn" type="submit">
          Apply
        </button>
      </form>

      <div className="grid">
        {(courses ?? []).map((course) => (
          <article className="card" key={course.id}>
            <span className="pill">{course.category_name ?? "General"}</span>
            <h3>{course.title}</h3>
            <p className="muted">{course.short_description ?? "No description yet."}</p>
            <p className="muted">
              Rating: {Number(course.review_avg ?? 0).toFixed(1)} ({course.review_count ?? 0})
            </p>
            <p>
              <strong>${Number(course.price ?? 0).toFixed(2)}</strong>
            </p>
            <Link href={`/courses/${course.slug}`} className="btn secondary">
              Details
            </Link>
          </article>
        ))}
      </div>
    </div>
  );
}

