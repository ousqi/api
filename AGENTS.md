# AGENTS.md

## 1. Core Principles

- Match workflow complexity to task complexity. Prefer the simplest correct solution and minimal changes.
- The main agent may directly inspect, edit, test, and review code. Do not delegate by default.
- Read only relevant files and reuse valid context, rules, documentation, and tool results already available.
- Prefer targeted searches and bounded tool output. Avoid unnecessary scans, repeated checks, abstractions, and unrelated refactoring.
- Preserve correctness, security, existing behavior, and meaningful verification. Efficiency must not override applicable project rules.
- Stop when the requested work and necessary verification are complete; report unresolved blockers.

## 2. Project Environment

- PHP 8.4, Laravel 13, Filament, PHPUnit, Laravel Pint.
- The project runs in WSL using Docker Compose. Run commands from the Compose project root in WSL.
- PHP service: `app`. Container project directory: `/var/www/html/api`.
- All PHP, Composer, Artisan, Pint, and PHPUnit commands must use this prefix:

```bash
docker compose exec -T -w /var/www/html/api app
```

- Never use host PHP or Composer. Check Compose configuration and service status when unknown, changed, or relevant to a failure.
- Use installed package APIs. Check the relevant lock file or a targeted package version only when needed; reuse known versions.
- Use `composer install` for existing dependencies. Do not change dependencies without approval; use `composer update` only for authorized dependency updates and inspect lock-file changes.

## 3. Proportional Workflow

### Simple Tasks

For clear, localized, low-risk changes (copy, styling, fields, small bug fixes): inspect relevant code, implement directly, and perform necessary verification. No formal plan, additional approval, sub-agent, or independent review is required.

### Standard Tasks

For features, business logic, APIs, or coordinated multi-file changes: inspect existing patterns, use a short plan when useful, implement, run affected tests, and review the final diff. No mandatory plan document or approval gate.

### Complex or High-Risk Tasks

For major architectural changes, security-sensitive behavior, breaking APIs, or destructive migrations: assess risks and dependencies, create a concise plan, and obtain confirmation before destructive or otherwise unapproved high-impact operations. Use independent review or sub-agents when their benefit justifies the cost. Perform verification proportional to the risk.

- Do not split work into unnecessary subtasks or require one agent per task.
- When delegating, provide only relevant paths, constraints, expected results, and acceptance criteria. Parallelize independent work only when useful.
- Consolidate confirmed findings and fix them together. Repeat review only where fixes invalidate previous results or introduce material risk.
- Create persistent plans or other documentation only when explicitly requested. If creating or updating an AI-generated plan, identify it as AI-generated and awaiting human review; never claim human approval that was not given.

## 4. Project Rules and Skills

- If `.ai/rules` exists, consult `.ai/rules/index.md` and read applicable path- or task-scoped rules before editing. Reuse loaded rules and inspect new rules only when scope changes or context is missing.
- Search additional rules when applicability is unclear; do not require a recursive grep for every edit.
- Load relevant skills for unfamiliar, complex, or framework-specific work when existing code and context are insufficient. Load any skill explicitly required by applicable higher-priority instructions.
- Reuse previously loaded skills; do not load unrelated skills or all referenced files by default.

## 5. Laravel Boost

Prefer relevant Boost tools when they provide more accurate or efficient project information.

- `database-schema`: inspect relevant schema before schema-dependent model or migration changes when the schema is not already known.
- `database-query`: use for read-only database queries instead of raw queries through Tinker.
- `get-absolute-url`: resolve project URLs before sharing them with the user.
- `browser-logs`: inspect recent relevant logs when diagnosing frontend problems.
- `search-docs`: use for uncertain Laravel ecosystem APIs, configuration, or version-specific behavior. Start with focused queries scoped to relevant packages; expand only if necessary. Reuse sufficient results and skip searches for unrelated changes or established local patterns.

## 6. Coding Conventions

- Follow existing directory structure, naming, architecture, and nearby code. Reuse existing components and services; do not introduce new base directories without approval.
- Use descriptive names, explicit parameter and return types, braces for control structures, and PHP 8 constructor property promotion where appropriate. Avoid unnecessary empty constructors.
- Follow existing enum conventions (prefer TitleCase for enum cases) and use PHPDoc, including array shapes, where it adds useful type information.
- Use Chinese comments for non-obvious business logic; avoid redundant comments.
- Prefer Artisan `make:` commands for framework-generated files and `make:class` for generic classes. Use `--no-interaction` for automated Artisan commands. Check command help only when needed.
- Follow existing API Resource, API versioning, named route, Eloquent, Service and Repository conventions. Do not introduce abstractions solely to satisfy a pattern.
- Add useful factories or seeders for new models where needed. Reuse existing factory states and Faker conventions.
- Prefer tests or existing Artisan commands to ad hoc verification scripts. Use Tinker only when useful; avoid creating database records without authorization. Quote Tinker expressions safely.
- If frontend assets are stale or the Vite manifest is missing, run the appropriate project build command or explain the required action.

## 7. Testing and Verification

- Add or update PHPUnit tests for meaningful behavior changes and important failure modes. Pure copy, styling, or layout-only changes do not require new tests.
- Follow existing test structure and use the testing skill when guidance is needed or explicitly required by applicable rules.
- After modifying PHP files, run the following in the project container:

```bash
docker compose exec -T -w /var/www/html/api app vendor/bin/pint --dirty --format agent
```

- Preserve unrelated user changes; if necessary, scope Pint to the PHP files modified for the current task.
- Run the narrowest relevant test set after behavior changes. Prefer a specific test file or `--filter`, using the project's PHPUnit setup:

```bash
docker compose exec -T -w /var/www/html/api app php artisan test --compact <test-file-or-filter>
```

- Use `php -l` when changed PHP files are not adequately loaded by tests or syntax validation is otherwise needed.
- Review the final diff for correctness, security, scope, and regressions. Expand testing when the impact warrants it.
- Do not run both Artisan tests and direct PHPUnit for the same coverage without a concrete reason. Do not repeat successful checks unless subsequent changes invalidate them.
- Fix confirmed in-scope failures. Report unrelated failures, unavailable tools, and incomplete verification without claiming success.

## 8. Safety and Scope

- Do not execute destructive database operations, irreversible migrations, or other unapproved high-impact actions. Verify the target environment before sensitive operations.
- Do not change public API contracts, authorization models, dependencies, or core architecture beyond the authorized task.
- Do not edit `vendor/` or generated files manually, expose secrets, or commit `.env` or credentials.
- Preserve existing user changes. Do not revert unrelated work.
- Ask for clarification only when missing information materially affects correctness or safety and cannot reasonably be resolved from the project.

## 9. Communication

- Reply in concise Simplified Chinese by default.
- For routine tasks, avoid lengthy plans, repeated progress narration, and unnecessary summaries.
- In the final response, briefly state the changes, relevant files, verification results, and remaining risks or blockers if any.
