<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPattern;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@nonax.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin', 'status' => 'active']
        );

        $teacherUser = User::updateOrCreate(
            ['email' => 'teacher@nonax.test'],
            ['name' => 'Dr. Ananya Rao', 'password' => Hash::make('password'), 'role' => 'teacher', 'mobile' => '9000000001', 'status' => 'active']
        );

        $teacher = TeacherProfile::updateOrCreate(
            ['user_id' => $teacherUser->id],
            ['employee_id' => 'EMP-1001', 'department' => 'Science', 'qualification' => 'M.Sc Physics', 'experience_years' => 8, 'status' => 'active']
        );

        $course = Course::updateOrCreate(
            ['code' => 'SSC-CGL'],
            ['name' => 'SSC CGL Preparation', 'description' => 'Staff Selection Commission CGL preparation.', 'duration' => '12 months', 'status' => 'active']
        );

        $teacher->courses()->syncWithoutDetaching([$course->id]);

        $batch = Batch::updateOrCreate(
            ['code' => 'SSC-A'],
            ['course_id' => $course->id, 'name' => 'Batch A', 'starts_on' => now()->toDateString(), 'status' => 'active']
        );

        $studentUser = User::updateOrCreate(
            ['email' => 'student@nonax.test'],
            ['name' => 'Riya Sharma', 'password' => Hash::make('password'), 'role' => 'student', 'mobile' => '9000000002', 'status' => 'active']
        );

        $student = StudentProfile::updateOrCreate(
            ['user_id' => $studentUser->id],
            ['student_id' => 'STD-1001', 'admission_number' => 'ADM-2026-0001', 'gender' => 'female', 'join_date' => now()->toDateString(), 'status' => 'active']
        );

        $student->courses()->syncWithoutDetaching([$course->id]);
        $student->batches()->syncWithoutDetaching([$batch->id]);

        $patterns = [
            'SSC_CGL_TIER1' => [
                'name' => 'SSC CGL Tier 1', 'timer_mode' => 'section', 'duration' => 60,
                'sections' => [
                    ['reasoning', 'General Intelligence & Reasoning', 25, 15, 2, 0.5, true, true],
                    ['general_awareness', 'General Awareness', 25, 15, 2, 0.5, true, true],
                    ['quant', 'Quantitative Aptitude', 25, 15, 2, 0.5, true, true],
                    ['english', 'English Comprehension', 25, 15, 2, 0.5, true, true],
                ],
            ],
            'SSC_CGL_TIER2_PAPER1' => [
                'name' => 'SSC CGL Tier 2 Paper 1', 'timer_mode' => 'section', 'duration' => 120,
                'sections' => [
                    ['section_1', 'Section I: Maths & Reasoning', 60, 60, 3, 1, true, true],
                    ['section_2', 'Section II: English & General Awareness', 70, 60, 3, 1, true, true],
                    ['computer', 'Section III: Computer Knowledge', 20, 15, 3, 0, true, true],
                    ['dest', 'Section III: Data Entry Speed Test', 0, 15, 0, 0, true, false],
                ],
            ],
            'RRB_NTPC_CBT1' => [
                'name' => 'RRB NTPC CBT 1', 'timer_mode' => 'global', 'duration' => 90,
                'sections' => [
                    ['general_awareness', 'General Awareness', 40, null, 1, 0.333333, false, true],
                    ['mathematics', 'Mathematics', 30, null, 1, 0.333333, false, true],
                    ['reasoning', 'General Intelligence & Reasoning', 30, null, 1, 0.333333, false, true],
                ],
            ],
            'RRB_NTPC_CBT2' => [
                'name' => 'RRB NTPC CBT 2', 'timer_mode' => 'global', 'duration' => 90,
                'sections' => [
                    ['general_awareness', 'General Awareness', 50, null, 1, 0.333333, false, true],
                    ['mathematics', 'Mathematics', 35, null, 1, 0.333333, false, true],
                    ['reasoning', 'General Intelligence & Reasoning', 35, null, 1, 0.333333, false, true],
                ],
            ],
        ];

        foreach ($patterns as $code => $definition) {
            $pattern = ExamPattern::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'timer_mode' => $definition['timer_mode'],
                    'duration_minutes' => $definition['duration'],
                    'settings' => str_starts_with($code, 'RRB_') ? ['normalization' => 'placeholder'] : null,
                    'is_active' => true,
                ]
            );
            foreach ($definition['sections'] as $position => $section) {
                $pattern->sections()->updateOrCreate(
                    ['key' => $section[0]],
                    [
                        'name' => $section[1], 'position' => $position + 1, 'question_count' => $section[2],
                        'duration_minutes' => $section[3], 'positive_marks' => $section[4],
                        'negative_marks' => $section[5], 'locks_on_exit' => $section[6], 'is_scored' => $section[7],
                    ]
                );
            }
        }

        $practiceSets = [
            'SSC_CGL_TIER1' => [
                'reasoning' => [
                    ['Find the odd one out: 8, 27, 64, 100, 125', ['A' => '8', 'B' => '27', 'C' => '100', 'D' => '125'], 'C', '100 is not a perfect cube.'],
                    ['Complete the series: 5, 11, 23, 47, ?', ['A' => '91', 'B' => '93', 'C' => '95', 'D' => '97'], 'C', 'Each term is multiplied by 2 and increased by 1.'],
                    ['If CAT is coded as DBU, how is DOG coded?', ['A' => 'EPH', 'B' => 'FQI', 'C' => 'CPH', 'D' => 'EOH'], 'A', 'Each letter moves forward by one.'],
                    ['Choose the missing term: 3, 9, 27, ?', ['A' => '54', 'B' => '72', 'C' => '81', 'D' => '90'], 'C', 'Each term is multiplied by 3.'],
                    ['Book is to Reading as Fork is to:', ['A' => 'Drawing', 'B' => 'Writing', 'C' => 'Eating', 'D' => 'Walking'], 'C', 'A fork is an instrument used for eating.'],
                ],
                'general_awareness' => [
                    ['The Sun appears to rise in the east because Earth rotates:', ['A' => 'East to west', 'B' => 'West to east', 'C' => 'North to south', 'D' => 'South to north'], 'B', 'Earth rotates from west to east.'],
                    ['Which Article guarantees equality before law?', ['A' => 'Article 14', 'B' => 'Article 19', 'C' => 'Article 21', 'D' => 'Article 32'], 'A', 'Article 14 guarantees equality before law.'],
                    ['The currency of Japan is:', ['A' => 'Won', 'B' => 'Yuan', 'C' => 'Yen', 'D' => 'Dollar'], 'C', 'Japan uses the yen.'],
                    ['The largest planet in the Solar System is:', ['A' => 'Earth', 'B' => 'Jupiter', 'C' => 'Saturn', 'D' => 'Mars'], 'B', 'Jupiter is the largest planet.'],
                    ['The national aquatic animal of India is:', ['A' => 'Blue whale', 'B' => 'Ganges river dolphin', 'C' => 'Crocodile', 'D' => 'Turtle'], 'B', 'The Ganges river dolphin is India’s national aquatic animal.'],
                ],
                'quant' => [
                    ['A sum becomes 3 times in 8 years at simple interest. When will it become 5 times?', ['A' => '12 years', 'B' => '16 years', 'C' => '20 years', 'D' => '24 years'], 'B', 'Interest of 2P takes 8 years, so interest of 4P takes 16 years.'],
                    ['The average of 5 numbers is 27. Four have average 25. Find the excluded number.', ['A' => '30', 'B' => '32', 'C' => '35', 'D' => '40'], 'C', '5×27 − 4×25 = 35.'],
                    ['A 150 m train crosses a pole in 10 seconds. Its speed is:', ['A' => '45 km/h', 'B' => '50 km/h', 'C' => '54 km/h', 'D' => '60 km/h'], 'C', '150/10 = 15 m/s = 54 km/h.'],
                    ['What is 25% of 240?', ['A' => '50', 'B' => '60', 'C' => '70', 'D' => '80'], 'B', 'One fourth of 240 is 60.'],
                    ['The ratio 18:24 in simplest form is:', ['A' => '2:3', 'B' => '3:4', 'C' => '4:5', 'D' => '6:8'], 'B', 'Divide both terms by 6.'],
                ],
                'english' => [
                    ['Choose the synonym of "Ubiquitous".', ['A' => 'Rare', 'B' => 'Omnipresent', 'C' => 'Hidden', 'D' => 'Unique'], 'B', 'Ubiquitous means present everywhere.'],
                    ['She has been working here ___ 2019.', ['A' => 'since', 'B' => 'for', 'C' => 'from', 'D' => 'at'], 'A', 'Since is used with a point in time.'],
                    ['Choose the correction: Each of the students have submitted the assignment.', ['A' => 'has submitted', 'B' => 'were submitted', 'C' => 'are submitting', 'D' => 'No correction'], 'A', 'Each takes a singular verb.'],
                    ['Choose the antonym of "Ancient".', ['A' => 'Old', 'B' => 'Historic', 'C' => 'Modern', 'D' => 'Past'], 'C', 'Modern is the opposite of ancient.'],
                    ['Choose the correctly spelled word.', ['A' => 'Accomodation', 'B' => 'Accommodation', 'C' => 'Acommodation', 'D' => 'Accommadation'], 'B', 'Accommodation has double c and double m.'],
                ],
            ],
            'RRB_NTPC_CBT1' => [
                'mathematics' => [
                    ['An article costs Rs. 400 and is sold at 15% profit. Find the selling price.', ['A' => 'Rs. 440', 'B' => 'Rs. 450', 'C' => 'Rs. 460', 'D' => 'Rs. 480'], 'C', '400 + 15% of 400 = 460.'],
                    ['Find the LCM of 12 and 18.', ['A' => '24', 'B' => '30', 'C' => '36', 'D' => '72'], 'C', 'The least common multiple is 36.'],
                    ['A takes 12 days and B takes 18 days. Together they take:', ['A' => '6 days', 'B' => '7.2 days', 'C' => '8 days', 'D' => '9 days'], 'B', 'Combined rate is 1/12 + 1/18 = 5/36.'],
                    ['What is 3/5 of 250?', ['A' => '100', 'B' => '125', 'C' => '150', 'D' => '175'], 'C', '250 × 3/5 = 150.'],
                    ['A number increased by 20% becomes 120. The number is:', ['A' => '90', 'B' => '96', 'C' => '100', 'D' => '110'], 'C', '120/1.2 = 100.'],
                ],
                'reasoning' => [
                    ['Find the next term: 2, 6, 12, 20, 30, ?', ['A' => '36', 'B' => '40', 'C' => '42', 'D' => '44'], 'C', 'Terms follow n×(n+1).'],
                    ['A man says, "She is the daughter of my grandfather’s only son." She is his:', ['A' => 'Mother', 'B' => 'Sister', 'C' => 'Aunt', 'D' => 'Daughter'], 'B', 'The grandfather’s only son is his father.'],
                    ['Which does not belong: Apple, Mango, Carrot, Banana?', ['A' => 'Apple', 'B' => 'Mango', 'C' => 'Carrot', 'D' => 'Banana'], 'C', 'Carrot is a vegetable; the others are fruits.'],
                    ['Complete the series: A, C, F, J, ?', ['A' => 'M', 'B' => 'N', 'C' => 'O', 'D' => 'P'], 'C', 'The gaps increase by one: +2, +3, +4, +5.'],
                    ['If SOUTH is written as TPVUI, NORTH is written as:', ['A' => 'OPSUI', 'B' => 'OPSTI', 'C' => 'NPSUI', 'D' => 'OQTUI'], 'A', 'Each letter moves forward by one.'],
                ],
                'general_awareness' => [
                    ['Who is known as the Father of the Indian Constitution?', ['A' => 'Mahatma Gandhi', 'B' => 'B. R. Ambedkar', 'C' => 'Jawaharlal Nehru', 'D' => 'Rajendra Prasad'], 'B', 'Dr. B. R. Ambedkar chaired the Drafting Committee.'],
                    ['The United Nations headquarters is in:', ['A' => 'Geneva', 'B' => 'Paris', 'C' => 'New York', 'D' => 'London'], 'C', 'The UN headquarters is in New York City.'],
                    ['The largest gland in the human body is:', ['A' => 'Pancreas', 'B' => 'Liver', 'C' => 'Thyroid', 'D' => 'Pituitary'], 'B', 'The liver is the largest gland.'],
                    ['India’s national anthem was written by:', ['A' => 'Bankim Chandra', 'B' => 'Rabindranath Tagore', 'C' => 'Sarojini Naidu', 'D' => 'Subramania Bharati'], 'B', 'Rabindranath Tagore wrote Jana Gana Mana.'],
                    ['The chemical symbol for gold is:', ['A' => 'Ag', 'B' => 'Gd', 'C' => 'Au', 'D' => 'Go'], 'C', 'Gold’s symbol Au comes from aurum.'],
                ],
            ],
        ];

        foreach ($practiceSets as $patternCode => $sectionsData) {
            $practiceCourse = Course::updateOrCreate(
                ['code' => str_starts_with($patternCode, 'SSC_') ? 'SSC-CGL' : 'RRB-NTPC'],
                ['name' => str_starts_with($patternCode, 'SSC_') ? 'SSC CGL Preparation' : 'RRB NTPC Preparation', 'duration' => '12 months', 'status' => 'active']
            );
            $pattern = ExamPattern::where('code', $patternCode)->firstOrFail();
            $teacher->courses()->syncWithoutDetaching([$practiceCourse->id]);
            $student->courses()->syncWithoutDetaching([$practiceCourse->id]);

            foreach ($sectionsData as $sectionKey => $questions) {
                $sectionDefinition = $pattern->sections()->where('key', $sectionKey)->firstOrFail();
                $subject = Subject::updateOrCreate(
                    ['course_id' => $practiceCourse->id, 'code' => strtoupper(substr($patternCode, 0, 3).'-'.substr($sectionKey, 0, 8))],
                    ['name' => $sectionDefinition->name, 'teacher_profile_id' => $teacher->id, 'status' => 'active']
                );
                foreach ($questions as $row) {
                    Question::updateOrCreate(
                        ['subject_id' => $subject->id, 'question' => $row[0]],
                        [
                            'course_id' => $practiceCourse->id, 'exam_pattern_id' => $pattern->id,
                            'section_key' => $sectionKey, 'language' => 'english', 'type' => 'mcq',
                            'options' => $row[1], 'correct_answer' => $row[2], 'difficulty' => 'medium',
                            'marks' => $sectionDefinition->positive_marks, 'negative_marks' => $sectionDefinition->negative_marks,
                            'explanation' => $row[3], 'topic' => $sectionDefinition->name, 'status' => 'active',
                        ]
                    );
                }
            }

            $exam = Exam::updateOrCreate(
                ['title' => $pattern->name.' Practice Demo', 'course_id' => $practiceCourse->id],
                [
                    'exam_pattern_id' => $pattern->id, 'created_by' => $admin->id, 'type' => 'mock_test',
                    'duration_minutes' => $pattern->duration_minutes,
                    'total_questions' => collect($sectionsData)->flatten(1)->count(),
                    'passing_marks' => 0, 'negative_marking' => true, 'status' => 'published',
                ]
            );
            $demoQuestions = Question::where('course_id', $practiceCourse->id)->where('exam_pattern_id', $pattern->id)->get();
            $exam->questions()->sync($demoQuestions->values()->mapWithKeys(fn ($question, $index) => [
                $question->id => ['position' => $index + 1, 'marks' => $question->marks],
            ])->all());
        }

        Exam::whereNull('exam_pattern_id')
            ->orWhereDoesntHave('pattern', fn ($query) => $query->whereIn('code', Exam::SUPPORTED_PATTERN_CODES))
            ->delete();
    }
}
