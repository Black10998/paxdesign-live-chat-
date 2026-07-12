import SwiftUI

enum PAXAppMark {
    static let cornerRadiusRatio: CGFloat = 0.223

    @ViewBuilder
    static func image(size: CGFloat) -> some View {
        PAXIcon( "bubble.left.and.bubble.right.fill")
            .font(.system(size: size * 0.52))
            .symbolRenderingMode(.hierarchical)
            .foregroundStyle(.tint)
            .frame(width: size, height: size)
    }
}

struct PAXAppMarkView: View {
    var size: CGFloat = 96
    var showGlow: Bool = false

    var body: some View {
        PAXAppMark.image(size: size)
    }
}
