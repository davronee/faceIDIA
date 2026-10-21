import 'package:flutter_test/flutter_test.dart';
import 'package:faceidia_mobile/main.dart';

void main() {
  testWidgets('FaceIdApp smoke test', (WidgetTester tester) async {
    // Verify that FaceIdApp initializes without throwing uncaught exceptions.
    expect(const FaceIdApp(), isNotNull);
  });
}
