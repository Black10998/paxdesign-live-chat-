import SwiftUI

enum PAXAppMark {
    static let cornerRadiusRatio: CGFloat = 0.223

    @ViewBuilder
    static func image(size: CGFloat) -> some View {
        let iconSize: PAXIconSize = size >= 40 ? .display : size >= 24 ? .hero : .card
        PAXIcon("house.fill", size: iconSize, tint: PAXBrand.adaptiveAccent)
            .scaleEffect(size / iconSize.length)
    }
}

struct PAXAppMarkView: View {
    var size: CGFloat = 96
    var showGlow: Bool = false

    var body: some View {
        PAXAnimatedLogoView(markWidth: max(size, 96))
            .frame(height: size)
    }
}
