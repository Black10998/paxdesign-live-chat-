import SwiftUI
import UIKit

struct PAXClockLogo: View {
    var size: CGFloat = 96
    var animate: Bool = false
    @State private var glow = false

    var body: some View {
        ZStack {
            Circle()
                .fill(PAXTheme.accentSoft)
                .frame(width: size * 1.35, height: size * 1.35)
                .blur(radius: glow ? 18 : 8)
                .opacity(glow ? 0.9 : 0.45)
                .animation(animate ? .easeInOut(duration: 1.8).repeatForever(autoreverses: true) : .default, value: glow)

            Circle()
                .stroke(PAXTheme.border, lineWidth: 1.5)
                .background(Circle().fill(PAXTheme.surface.opacity(0.85)))
                .frame(width: size, height: size)

            Circle()
                .stroke(PAXTheme.accent.opacity(0.35), lineWidth: 1)
                .frame(width: size * 0.72, height: size * 0.72)

            Image(systemName: "clock.fill")
                .font(.system(size: size * 0.34, weight: .light))
                .foregroundStyle(PAXTheme.textPrimary.opacity(0.88))

            Text("Chat")
                .font(.system(size: size * 0.11, weight: .semibold, design: .rounded))
                .foregroundStyle(PAXTheme.textTertiary)
                .offset(y: size * 0.02)
        }
        .onAppear {
            if animate { glow = true }
        }
    }
}

struct PAXGlassCard<Content: View>: View {
    let content: Content

    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }

    var body: some View {
        content
            .padding(16)
            .background(
                RoundedRectangle(cornerRadius: 18, style: .continuous)
                    .fill(PAXTheme.surface.opacity(0.92))
                    .overlay(
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .stroke(PAXTheme.border, lineWidth: 1)
                    )
            )
    }
}

struct PAXField: View {
    let title: String
    let icon: String
    @Binding var text: String
    var isSecure = false
    var keyboardType: UIKeyboardType = .default

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Label(title, systemImage: icon)
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textSecondary)

            Group {
                if isSecure {
                    SecureField("", text: $text)
                } else {
                    TextField("", text: $text)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .keyboardType(keyboardType)
                }
            }
            .font(.body)
            .padding(.horizontal, 14)
            .padding(.vertical, 13)
            .background(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .fill(PAXTheme.surfaceElevated)
                    .overlay(
                        RoundedRectangle(cornerRadius: 14, style: .continuous)
                            .stroke(PAXTheme.border, lineWidth: 1)
                    )
            )
        }
    }
}

struct PAXPrimaryButton: View {
    let title: String
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 10) {
                if isLoading {
                    ProgressView()
                        .tint(.black)
                }
                Text(title)
                    .font(.headline)
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 16)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(
                        LinearGradient(
                            colors: [.white, Color.white.opacity(0.92)],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
            )
            .foregroundStyle(.black)
            .shadow(color: PAXTheme.accent.opacity(0.18), radius: 16, y: 8)
        }
        .buttonStyle(PAXPressButtonStyle())
        .disabled(isLoading)
    }
}

struct PAXStatusBadge: View {
    let text: String
    let color: Color

    var body: some View {
        Text(text)
            .font(.caption2.weight(.bold))
            .padding(.horizontal, 10)
            .padding(.vertical, 5)
            .background(Capsule().fill(color.opacity(0.16)))
            .foregroundStyle(color)
    }
}

struct PAXPressButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .animation(PAXTheme.quickSpring, value: configuration.isPressed)
    }
}

struct PAXAvatar: View {
    let name: String
    var size: CGFloat = 40

    private var initials: String {
        let parts = name.split(separator: " ")
        let letters = parts.prefix(2).compactMap { $0.first }
        return letters.isEmpty ? "P" : String(letters).uppercased()
    }

    var body: some View {
        ZStack {
            Circle()
                .fill(
                    LinearGradient(
                        colors: [PAXTheme.accent.opacity(0.85), PAXTheme.accent.opacity(0.45)],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
            Text(initials)
                .font(.system(size: size * 0.34, weight: .semibold, design: .rounded))
                .foregroundStyle(.white)
        }
        .frame(width: size, height: size)
    }
}

struct PAXSplashView: View {
    @State private var appear = false

    var body: some View {
        ZStack {
            PAXBackground()
            VStack(spacing: 18) {
                Group {
                    if let icon = UIImage(named: "AppIcon") {
                        Image(uiImage: icon)
                            .resizable()
                            .scaledToFit()
                            .frame(width: 112, height: 112)
                            .clipShape(RoundedRectangle(cornerRadius: 26, style: .continuous))
                    } else {
                        PAXClockLogo(size: 112, animate: true)
                    }
                }
                .scaleEffect(appear ? 1 : 0.82)
                .opacity(appear ? 1 : 0)

                VStack(spacing: 6) {
                    Text("PAXDesign")
                        .font(.system(size: 28, weight: .semibold, design: .rounded))
                    Text("Live Chat")
                        .font(.subheadline.weight(.medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                .opacity(appear ? 1 : 0)
                .offset(y: appear ? 0 : 8)
            }
        }
        .onAppear {
            withAnimation(PAXTheme.spring) { appear = true }
        }
    }
}
