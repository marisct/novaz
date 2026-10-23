import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_app/main.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('shows NOVA Z login screen', (tester) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const NovaXApp());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('NOVA Z'), findsWidgets);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
