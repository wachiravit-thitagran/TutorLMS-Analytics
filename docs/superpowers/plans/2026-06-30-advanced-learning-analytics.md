# Advanced Learning Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add instructor-focused cohort, retention, engagement segment, content effectiveness, and quiz diagnostic analytics to TutorLMS Analytics.

**Architecture:** Keep the existing provider-based architecture. Add focused providers for cohort and assessment analytics, extend existing engagement/content/time providers where responsibilities already exist, then expose the data through `Admin_Menu` and the single-course dashboard.

**Tech Stack:** WordPress plugin PHP 7.4+, Tutor LMS database tables, PHPUnit 9.6, Chart.js, Alpine.js.

---

## File Structure

- Create: `includes/Providers/Cohort_Provider.php` — enrollment cohort completion and weekly retention.
- Modify: `includes/Providers/Engagement_Provider.php` — engagement trend, stuck learners, power learners, low-engagement-high-score learners.
- Modify: `includes/Providers/Time_Analytics_Provider.php` — lesson revisit/rewatch and time-to-complete data.
- Modify: `includes/Providers/Content_Gap_Provider.php` — exit lesson, content difficulty index, content engagement index.
- Modify: `includes/Providers/Quiz_Provider.php` — question difficulty, common wrong answers, attempts before pass, retry behavior.
- Modify: `includes/Admin_Menu.php` — instantiate and pass new analytics arrays.
- Modify: `views/admin-dashboard-single.php` — add tab sections/cards/tables for new analytics.
- Create: `tests/CohortProviderTest.php` — TDD tests for cohort provider.
- Create: `tests/AdvancedAnalyticsProviderTest.php` — TDD tests for new provider outputs and empty-state contracts.
- Modify: `tests/bootstrap.php` — add missing WordPress stubs only if tests require them.

## Data Contracts

`$stats['cohort_analytics']`:

```php
array(
    'completion_by_enrollment_cohort' => array(
        array('cohort' => '2026-01', 'enrolled' => 25, 'completed' => 10, 'completion_rate' => 40.0),
    ),
    'retention_by_week' => array(
        array('week' => 'Week 1', 'active_learners' => 20, 'retention_rate' => 80.0),
    ),
)
```

`$stats['engagement']` additions:

```php
array(
    'engagement_trends' => array(array('user_id' => 1, 'display_name' => 'Jane', 'events_7d' => 8, 'events_prev_7d' => 3, 'trend' => 'up', 'change_pct' => 166.7)),
    'high_intent_stuck' => array(array('user_id' => 2, 'display_name' => 'Sam', 'events_14d' => 30, 'progress_pct' => 25.0, 'last_activity' => '2026-06-29 10:00:00')),
    'power_learners' => array(array('user_id' => 3, 'display_name' => 'Ann', 'progress_pct' => 100.0, 'quiz_avg_score' => 92.5, 'days_to_complete' => 4)),
    'low_engagement_high_score' => array(array('user_id' => 4, 'display_name' => 'Tom', 'score' => 28, 'quiz_avg_score' => 90.0)),
)
```

`$stats['time_analytics']` additions:

```php
array(
    'lesson_revisit_rate' => array(array('lesson_id' => 10, 'title' => 'Intro', 'unique_learners' => 10, 'total_views' => 25, 'revisit_rate' => 150.0)),
    'time_to_complete_per_lesson' => array(array('lesson_id' => 10, 'title' => 'Intro', 'avg_minutes' => 12.5, 'sample_count' => 8)),
)
```

`$stats['content_gaps']` additions:

```php
array(
    'exit_lessons' => array(array('lesson_id' => 10, 'title' => 'Intro', 'exit_count' => 8, 'exit_rate' => 32.0)),
    'difficulty_index' => array(array('content_id' => 10, 'title' => 'Intro', 'type' => 'lesson', 'score' => 76, 'signals' => array('dropoff_pct' => 30, 'avg_time_minutes' => 22, 'quiz_avg_score' => 55))),
    'engagement_index' => array(array('content_id' => 10, 'title' => 'Intro', 'type' => 'lesson', 'score' => 68, 'signals' => array('completion_pct' => 70, 'revisit_rate' => 40, 'continuation_pct' => 65))),
)
```

`$stats['quiz_diagnostics']`:

```php
array(
    'question_difficulty' => array(array('question_id' => 55, 'title' => 'Question 1', 'quiz_title' => 'Quiz A', 'correct_rate' => 42.0, 'attempts' => 50)),
    'common_wrong_answers' => array(array('question_id' => 55, 'answer' => 'Wrong B', 'selected_count' => 20)),
    'attempts_before_pass' => array(array('quiz_id' => 33, 'title' => 'Quiz A', 'avg_attempts_before_pass' => 2.4, 'passed_users' => 15)),
    'retry_behavior' => array(array('quiz_id' => 33, 'title' => 'Quiz A', 'failed_users' => 20, 'retried_users' => 12, 'retry_rate' => 60.0)),
)
```

### Task 1: Cohort Provider

**Files:**
- Create: `includes/Providers/Cohort_Provider.php`
- Create: `tests/CohortProviderTest.php`
- Modify: `includes/Admin_Menu.php`

- [ ] **Step 1: Write failing tests**

Create tests that assert empty state and expected output keys for `get_cohort_analytics(123)`.

- [ ] **Step 2: Run tests to verify failure**

Run: `composer test -- --filter CohortProviderTest`
Expected: FAIL because `TutorLMS_Analytics\Providers\Cohort_Provider` does not exist.

- [ ] **Step 3: Implement provider**

Implement `get_completion_by_enrollment_cohort(int $course_id = 0, int $months = 12)` using `tutor_enrolled` post month and `course_completed` comments. Implement `get_retention_by_week(int $course_id = 0, int $weeks = 8)` using enrollment count as denominator and analytics event activity by week after enrollment.

- [ ] **Step 4: Run tests**

Run: `composer test -- --filter CohortProviderTest`
Expected: PASS.

### Task 2: Engagement Segments

**Files:**
- Modify: `includes/Providers/Engagement_Provider.php`
- Create/Modify: `tests/AdvancedAnalyticsProviderTest.php`

- [ ] **Step 1: Write failing tests**

Assert `get_engagement_data(123)` includes `engagement_trends`, `high_intent_stuck`, `power_learners`, and `low_engagement_high_score` keys with arrays.

- [ ] **Step 2: Run failing tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: FAIL because keys are missing.

- [ ] **Step 3: Implement engagement analytics**

Add public helper methods and wire them into `get_engagement_data()`:
- `get_engagement_trends()` compares events in last 7 days vs previous 7 days.
- `get_high_intent_stuck_students()` finds learners with high 14-day events and progress below 50%.
- `get_power_learners()` finds learners completed quickly with quiz average >= 85%.
- `get_low_engagement_high_score_students()` filters existing scores with score < 40 and quiz average >= 85%.

- [ ] **Step 4: Run tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: PASS for engagement tests.

### Task 3: Content Time and Revisit Analytics

**Files:**
- Modify: `includes/Providers/Time_Analytics_Provider.php`
- Modify: `tests/AdvancedAnalyticsProviderTest.php`

- [ ] **Step 1: Write failing tests**

Assert `get_time_analytics(123)` includes `lesson_revisit_rate` and `time_to_complete_per_lesson` arrays.

- [ ] **Step 2: Run failing tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: FAIL because keys are missing.

- [ ] **Step 3: Implement methods**

Use `lesson_view`/`page_exit` events. Revisit rate is `(total_views - unique_learners) / unique_learners * 100`. Time-to-complete per lesson uses `page_exit` event seconds, filtered to 1..7200 seconds.

- [ ] **Step 4: Run tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: PASS for time analytics tests.

### Task 4: Content Gap Indexes

**Files:**
- Modify: `includes/Providers/Content_Gap_Provider.php`
- Modify: `tests/AdvancedAnalyticsProviderTest.php`

- [ ] **Step 1: Write failing tests**

Assert `get_content_gaps(123)` includes `exit_lessons`, `difficulty_index`, and `engagement_index` arrays.

- [ ] **Step 2: Run failing tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: FAIL because keys are missing.

- [ ] **Step 3: Implement methods**

Calculate exit lessons from last `page_exit` per user. Difficulty index combines drop-off, high time, and low quiz score signals. Engagement index combines completion, revisit, and continuation signals. Clamp scores to 0..100.

- [ ] **Step 4: Run tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: PASS for content gap tests.

### Task 5: Quiz Diagnostics

**Files:**
- Modify: `includes/Providers/Quiz_Provider.php`
- Modify: `includes/Admin_Menu.php`
- Modify: `tests/AdvancedAnalyticsProviderTest.php`

- [ ] **Step 1: Write failing tests**

Assert `get_quiz_diagnostics(123)` returns `question_difficulty`, `common_wrong_answers`, `attempts_before_pass`, and `retry_behavior` keys.

- [ ] **Step 2: Run failing tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: FAIL because `get_quiz_diagnostics()` does not exist.

- [ ] **Step 3: Implement diagnostics**

Use Tutor LMS quiz attempt tables when available. Guard every optional table with `SHOW TABLES LIKE`. Return empty arrays if question/answer tables are unavailable.

- [ ] **Step 4: Run tests**

Run: `composer test -- --filter AdvancedAnalyticsProviderTest`
Expected: PASS for quiz diagnostics tests.

### Task 6: Dashboard UI

**Files:**
- Modify: `views/admin-dashboard-single.php`

- [ ] **Step 1: Add display sections**

Add new tabs/cards for:
- Cohorts & Retention
- Learner Segments
- Content Quality
- Quiz Diagnostics

- [ ] **Step 2: Validate syntax**

Run: `composer run syntax`
Expected: all files report `No syntax errors detected`.

- [ ] **Step 3: Run full tests**

Run: `composer test`
Expected: PASS.

## Self-Review

- Spec coverage: All requested metrics are represented in provider contracts and UI tasks.
- Placeholder scan: No task says to leave behavior undefined; all metrics have formulas/data sources.
- Type consistency: Stats keys match planned dashboard keys.
