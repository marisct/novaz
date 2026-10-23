NovaX IMS Flutter App
=====================

API URL
-------
https://novaz.in/api

Default seeded logins
---------------------
Admin:   admin@nonax.test / password
Teacher: teacher@nonax.test / password
Student: student@nonax.test / password

Integrated API modules
----------------------
Dashboard/Profile:
/api/profile
/api/dashboard

Management:
/api/management/students
/api/management/teachers
/api/management/courses
/api/management/subjects
/api/management/questions
/api/management/exams
/api/management/attendance
/api/management/materials
/api/management/meta

Flutter app features
--------------------
- Login with bearer token
- Role-based dashboard
- Read pages for courses, exams, attendance, notes
- Admin management: students, teachers, courses, subjects, questions, exams, attendance, materials
- Teacher management: students, questions, exams, attendance, materials
- Student read-only access
- Add, edit, and delete where the API role allows it

Run in development
------------------
flutter run --dart-define=API_BASE_URL=https://novaz.in/api

Build Android APK
-----------------
flutter build apk --release --dart-define=API_BASE_URL=https://novaz.in/api

APK output
----------
build\app\outputs\flutter-apk\app-release.apk
