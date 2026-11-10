<?php
use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Providers\Student_Provider;
use TutorLMS_Analytics\Export_Handler;
final class StudentRegressionTest extends TestCase {
 public function test_course_progress_never_inherits_another_course() : void {
  global $wpdb;
  $wpdb = new Mock_WPDB();
  $wpdb->mock_results['MAX(p.post_date)'] = [['user_id'=>731,'last_enrolled'=>'2026-11-09','courses_taken'=>1]];
  $GLOBALS['mock_users'][731] = (object)['display_name'=>'ผู้เรียน','user_email'=>'learner@example.test'];
  $GLOBALS['mock_user_meta'][731]['_tutor_course_progress'] = [200=>['completed_lesson'=>10,'total_lesson'=>10]];
  $row = (new Student_Provider())->get_student_table(100)[0];
  self::assertSame(0.0, $row['avg_progress']);
  self::assertSame(0, $row['completed_lesson']);
 }
 public function test_quiz_stats_follow_topic_course_hierarchy() : void {
  global $wpdb;
  $wpdb = new Mock_WPDB();
  $wpdb->mock_results['MAX(p.post_date)'] = [['user_id'=>731,'last_enrolled'=>'2026-11-09','courses_taken'=>1]];
  $GLOBALS['mock_users'][731] = (object)['display_name'=>'ผู้เรียน','user_email'=>'learner@example.test'];
  $wpdb->mock_var['SHOW TABLES LIKE'] = 'wp_tutor_quiz_attempts';
  $wpdb->mock_results['topic.post_parent = 100'] = [['user_id'=>731,'total_attempts'=>2,'avg_score'=>75]];
  $row=(new Student_Provider())->get_student_table(100)[0];
  self::assertSame(2,$row['quiz_attempts']); self::assertSame(75.0,$row['quiz_avg_score']);
 }
 public function test_spreadsheet_strings_are_literal() : void {
  foreach (['=1+1', '+1', '-2', '@SUM(A1)', "\t =1+1", "\r=1"] as $value) self::assertSame("'".$value, Export_Handler::csv_cell($value));
  self::assertSame('ทดสอบ',Export_Handler::csv_cell('ทดสอบ'));
  self::assertSame(12,Export_Handler::csv_cell(12));
 }
}
