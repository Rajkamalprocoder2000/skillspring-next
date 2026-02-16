import { createServerSupabaseClient } from "@/lib/supabase/server";

type CheckResult = {
  label: string;
  ok: boolean;
  detail: string;
};

export default async function SetupPage() {
  const results: CheckResult[] = [];

  const hasUrl = Boolean(process.env.NEXT_PUBLIC_SUPABASE_URL);
  const hasAnon = Boolean(process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY);
  results.push({
    label: "Environment",
    ok: hasUrl && hasAnon,
    detail: hasUrl && hasAnon ? "Supabase env vars are configured." : "Missing NEXT_PUBLIC_SUPABASE_URL or NEXT_PUBLIC_SUPABASE_ANON_KEY.",
  });

  if (!hasUrl || !hasAnon) {
    return (
      <div style={{ paddingBottom: 24 }}>
        <h1>Setup Check</h1>
        <section className="card">
          {results.map((r) => (
            <p key={r.label} style={{ color: r.ok ? "#166534" : "#b91c1c" }}>
              <strong>{r.label}:</strong> {r.detail}
            </p>
          ))}
        </section>
      </div>
    );
  }

  try {
    const supabase = await createServerSupabaseClient();
    const checks: Array<{ table: string; label: string }> = [
      { table: "profiles", label: "Profiles table" },
      { table: "categories", label: "Categories table" },
      { table: "courses", label: "Courses table" },
      { table: "course_sections", label: "Course sections table" },
      { table: "course_lessons", label: "Course lessons table" },
      { table: "enrollments", label: "Enrollments table" },
      { table: "lesson_progress", label: "Lesson progress table" },
      { table: "reviews", label: "Reviews table" },
      { table: "course_approval_logs", label: "Approval logs table" },
    ];

    for (const check of checks) {
      const { error } = await supabase.from(check.table).select("*").limit(1);
      results.push({
        label: check.label,
        ok: !error,
        detail: error ? error.message : "OK",
      });
    }
  } catch (error) {
    results.push({
      label: "Supabase connection",
      ok: false,
      detail: error instanceof Error ? error.message : "Unknown connection error",
    });
  }

  const allOk = results.every((r) => r.ok);

  return (
    <div style={{ paddingBottom: 24 }}>
      <h1>Setup Check</h1>
      <p className="muted">Run this page after configuring Supabase and running schema SQL.</p>
      <section className="card">
        {results.map((r) => (
          <p key={r.label} style={{ color: r.ok ? "#166534" : "#b91c1c" }}>
            <strong>{r.label}:</strong> {r.detail}
          </p>
        ))}
      </section>
      {!allOk && (
        <section className="card" style={{ marginTop: 12 }}>
          <p>
            Fix any failing checks, then rerun <code>/setup</code>.
          </p>
          <p className="muted">
            If tables are missing, run <code>supabase/schema.sql</code> in Supabase SQL Editor.
          </p>
        </section>
      )}
    </div>
  );
}

