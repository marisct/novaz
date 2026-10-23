<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('timer_mode')->default('global');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_pattern_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('positive_marks', 8, 2)->default(1);
            $table->decimal('negative_marks', 8, 4)->default(0);
            $table->boolean('locks_on_exit')->default(false);
            $table->boolean('is_scored')->default(true);
            $table->timestamps();
            $table->unique(['exam_pattern_id', 'key']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('exam_pattern_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('exam_pattern_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->string('section_key')->nullable()->after('exam_pattern_id')->index();
            $table->string('language')->default('english')->after('section_key')->index();
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('current_section_key')->nullable()->after('started_at');
            $table->timestamp('section_started_at')->nullable()->after('current_section_key');
            $table->json('section_times')->nullable();
            $table->json('locked_sections')->nullable();
            $table->json('section_scores')->nullable();
            $table->decimal('accuracy_percent', 5, 2)->default(0);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->string('section_key')->nullable()->after('question_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', fn (Blueprint $table) => $table->dropColumn('section_key'));
        Schema::table('exam_attempts', fn (Blueprint $table) => $table->dropColumn([
            'current_section_key', 'section_started_at', 'section_times', 'locked_sections',
            'section_scores', 'accuracy_percent',
        ]));
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_pattern_id');
            $table->dropColumn(['section_key', 'language']);
        });
        Schema::table('exams', fn (Blueprint $table) => $table->dropConstrainedForeignId('exam_pattern_id'));
        Schema::dropIfExists('exam_sections');
        Schema::dropIfExists('exam_patterns');
    }
};
