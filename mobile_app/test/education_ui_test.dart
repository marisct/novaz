import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_app/main.dart';
import 'package:shared_preferences/shared_preferences.dart';

class StudyApi extends ApiClient {
  StudyApi() : super('https://example.test/api');
  @override
  Future<dynamic> get(String token, String endpoint) async {
    if (endpoint == '/home') {
      return {
        'profile': {},
        'dashboard': {
          'completed_tests': 4,
          'average_score': 72,
          'attendance_percentage': 96,
          'weekly_performance': {'history': []},
          'exam_notifications': [],
          'subject_performance': [
            {
              'subject': 'Quantitative Aptitude',
              'course': 'SSC CGL Preparation',
              'questions': 25,
              'correct': 18,
              'wrong': 5,
              'skipped': 2,
              'accuracy': 78.3,
            },
            {
              'subject': 'English Comprehension',
              'course': 'SSC CGL Preparation',
              'questions': 25,
              'correct': 9,
              'wrong': 12,
              'skipped': 4,
              'accuracy': 42.9,
            },
            {
              'subject': 'General Awareness',
              'course': 'SSC CGL Preparation',
              'questions': 0,
              'correct': 0,
              'wrong': 0,
              'skipped': 0,
              'accuracy': null,
            },
          ],
        },
      };
    }
    return {
      'data': [
        {
          'id': 1,
          'name': 'SSC CGL Preparation',
          'subjects': [
            {'id': 2, 'name': 'Quantitative Aptitude'},
          ],
        },
      ],
    };
  }
}

void main() {
  for (final width in [390.0, 1000.0]) {
    testWidgets('education UI and course navigation at $width', (tester) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final font = File('C:/Windows/Fonts/segoeui.ttf');
      if (font.existsSync()) {
        final loader = FontLoader('Roboto')
          ..addFont(Future.value(ByteData.sublistView(font.readAsBytesSync())));
        await loader.load();
      }
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(const NovaXApp());
      await tester.pumpAndSettle();
      await tester.runAsync(() async {
        final icons = FontLoader('MaterialIcons')
          ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
        await icons.load();
        final context = tester.element(find.byType(Scaffold).first);
        await precacheImage(const AssetImage('assets/study-desk.jpg'), context);
        if (context.mounted) {
          await precacheImage(
            const AssetImage('assets/novax-logo.png'),
            context,
          );
        }
      });
      await tester.pumpAndSettle();
      final theme = tester.widget<MaterialApp>(find.byType(MaterialApp)).theme;
      expect(tester.takeException(), isNull);
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/login-$width.png'),
      );
      await tester.pumpWidget(
        MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: theme,
          home: HomeScreen(
            api: StudyApi(),
            session: const Session(
              token: 'test',
              user: {'name': 'Riya Sharma', 'role': 'student'},
            ),
            onLogout: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/home-$width.png'),
      );
      // Subject-wise performance sits below the fold: scroll to it and check it renders.
      await tester.scrollUntilVisible(
        find.text('Subject-wise performance'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(
        find.textContaining('1 more subject has no test results'),
      );
      await tester.pumpAndSettle();
      expect(find.text('Quantitative Aptitude'), findsWidgets);
      expect(find.text('78.3%'), findsOneWidget);
      expect(find.text('42.9%'), findsOneWidget);
      expect(find.textContaining('18 correct'), findsOneWidget);
      expect(
        find.textContaining('1 more subject has no test results'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/subjects-$width.png'),
      );
      await tester.tap(find.text('Courses').last);
      await tester.pumpAndSettle();
      expect(find.text('SSC CGL Preparation'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'unmatched');
      await tester.pumpAndSettle();
      expect(find.text('No courses match your search.'), findsOneWidget);
      await tester.enterText(find.byType(TextField), 'SSC');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Explore course'));
      await tester.pumpAndSettle();
      expect(find.text('Quantitative Aptitude'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
