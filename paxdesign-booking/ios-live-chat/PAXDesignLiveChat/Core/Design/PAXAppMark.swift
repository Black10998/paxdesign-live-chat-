import SwiftUI

enum PAXAppMark {
    static let cornerRadiusRatio: CGFloat = 0.223

    @ViewBuilder
    static func image(size: CGFloat) -> some View {
        Image("AppMark")
            .resizable()
            .scaledToFit()
            .frame(width: size, height: size)
            .clipShape(RoundedRectangle(cornerRadius: size * cornerRadiusRatio, style: .continuous))
    }
}

struct PAXAppMarkView: View {
    var size: CGFloat = 96
    var showGlow: Bool = false
    @State private var glowPulse = false

    var body: some View {
        ZStack {
            if showGlow {
                RoundedRectangle(cornerRadius: size * PAXAppMark.cornerRadiusRatio, style: .continuous)
                    .fill(PAXTheme.accent.opacity(glowPulse ? 0.22 : 0.08))
                    .frame(width: size * 1.28, height: size * 1.28)
                    .blur(radius: glowPulse ? 28 : 16)
                    .animation(.easeInOut(duration: 2.0).repeatForever(autoreverses: true), value: glowPulse)
            }
            PAXAppMark.image(size: size)
                .shadow(color: .black.opacity(0.35), radius: 24, y: 12)
        }
        .onAppear {
            if showGlow { glowPulse = true }
        }
    }
}
