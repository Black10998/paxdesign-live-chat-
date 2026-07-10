import SwiftUI

enum PAXTextAlignment {
    static func containsArabicScript(_ text: String) -> Bool {
        text.unicodeScalars.contains { scalar in
            (0x0600...0x06FF).contains(scalar.value)
                || (0x0750...0x077F).contains(scalar.value)
                || (0x08A0...0x08FF).contains(scalar.value)
        }
    }

    static func natural(for text: String) -> TextAlignment {
        containsArabicScript(text) ? .trailing : .leading
    }

    static func layoutDirection(for text: String) -> LayoutDirection {
        containsArabicScript(text) ? .rightToLeft : .leftToRight
    }
}
