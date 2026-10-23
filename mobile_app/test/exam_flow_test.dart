import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_app/main.dart';

Map<String, dynamic> paper({bool sessionBreak = false}) => {
  'status': 'attempt',
  'attempt': {
    'id': 1,
    'current_section_key': sessionBreak ? 'dest' : 'reasoning',
  },
  'exam': {
    'title': 'SSC CGL Tier 2 Paper 1 (2026)',
    'dest_passage': 'Practice passage.',
    'pattern': {
      'timer_mode': 'section',
      'settings': {'official_mock': true},
    },
  },
  'remaining_seconds': sessionBreak ? 0 : 900,
  'session_break': sessionBreak,
  'typing_session': sessionBreak,
  'answers': <String, dynamic>{},
  'questions': sessionBreak
      ? []
      : [
          {
            'id': 1,
            'question': 'Choose the correct answer.',
            'options': {
              'A': 'First choice',
              'B': 'Second choice',
              'C': 'Third choice',
              'D': 'Fourth choice',
            },
          },
        ],
};

class ExamApi extends ApiClient {
  ExamApi() : super('https://example.test/api');
  final calls = <Map<String, dynamic>>[];
  @override
  Future<dynamic> post(
    String token,
    String endpoint,
    Map<String, dynamic> payload,
  ) async {
    calls.add(payload);
    if (payload['action'] == 'start_dest') {
      return {
        ...paper(sessionBreak: true),
        'session_break': false,
        'remaining_seconds': 900,
      };
    }
    return paper();
  }
}

void main() {
  testWidgets('expired paper automatically requests server submission', (
    tester,
  ) async {
    final api = ExpiredExamApi();
    await tester.pumpWidget(
      MaterialApp(
        home: ExamTakeScreen(
          api: api,
          token: 'test',
          payload: {...paper(), 'remaining_seconds': 0},
        ),
      ),
    );
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(api.action, 'continue');
    expect(find.text('Result'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
  });
  for (final width in [360.0, 1000.0]) {
    testWidgets(
      'official exam renders at $width and autosaves selected answers',
      (tester) async {
        await tester.binding.setSurfaceSize(Size(width, 800));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final api = ExamApi();
        await tester.pumpWidget(
          MaterialApp(
            home: ExamTakeScreen(api: api, token: 'test', payload: paper()),
          ),
        );
        expect(tester.takeException(), isNull);
        final next = tester.widget<FilledButton>(
          find.widgetWithText(FilledButton, 'Timed section'),
        );
        expect(next.onPressed, isNull);
        await tester.tap(find.text('A. First choice'));
        await tester.pump(const Duration(milliseconds: 400));
        await tester.pump();
        expect(api.calls.single['answers'], {'1': 'A'});
        expect(api.calls.single['section_key'], 'reasoning');
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }
  testWidgets('typing begins only after the separate session is started', (
    tester,
  ) async {
    final api = ExamApi();
    await tester.pumpWidget(
      MaterialApp(
        home: ExamTakeScreen(
          api: api,
          token: 'test',
          payload: paper(sessionBreak: true),
        ),
      ),
    );
    expect(find.byType(TextField), findsNothing);
    await tester.tap(find.text('Start typing session'));
    await tester.pump();
    await tester.pump();
    expect(api.calls.single['action'], 'start_dest');
    expect(find.byType(TextField), findsOneWidget);
    expect(find.text('Practice passage.'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });
}

class ExpiredExamApi extends ApiClient {
  ExpiredExamApi() : super('https://example.test/api');
  String? action;
  @override
  Future<dynamic> post(
    String token,
    String endpoint,
    Map<String, dynamic> payload,
  ) async {
    action = payload['action'] as String;
    return {
      'status': 'result',
      'attempt': {'score': 0},
      'exam': {'title': 'Finished mock'},
    };
  }
}
