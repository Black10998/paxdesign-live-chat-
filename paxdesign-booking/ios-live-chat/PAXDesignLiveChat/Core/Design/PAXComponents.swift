import SwiftUI
import UIKit

struct PAXGlassCard<Content: View>: View {
    let content: Content

    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }

    var body: some View {
        content
            .padding(16)
            .paxGlassCardStyle(cornerRadius: 14, fillOpacity: 0.82, borderOpacity: 0.46, shadowOpacity: 0.18)
    }
}

struct PAXField: View {
    let title: String
    let icon: String
    @Binding var text: String
    var isSecure = false
    var keyboardType: UIKeyboardType = .default

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Label(title, systemImage: icon)
                .font(.subheadline)
                .foregroundStyle(.secondary)

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
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .paxGlassCardStyle(cornerRadius: 12, fillOpacity: 0.76, borderOpacity: 0.4, shadowOpacity: 0.1)
        }
    }
}

struct PAXPrimaryButton: View {
    let title: String
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 8) {
                if isLoading {
                    ProgressView()
                }
                Text(title)
                    .fontWeight(.semibold)
            }
            .frame(maxWidth: .infinity)
        }
        .buttonStyle(.borderedProminent)
        .controlSize(.large)
        .disabled(isLoading)
    }
}

struct PAXStatusBadge: View {
    let text: String
    let color: Color

    var body: some View {
        Text(text)
            .font(.caption2.weight(.semibold))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(color.opacity(0.12), in: Capsule())
            .foregroundStyle(color)
    }
}

struct PAXPressButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .opacity(configuration.isPressed ? 0.85 : 1)
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
                .fill(Color.accentColor.opacity(0.85))
            Text(initials)
                .font(.system(size: size * 0.34, weight: .semibold))
                .foregroundStyle(.white)
        }
        .frame(width: size, height: size)
    }
}

private struct PAXSkeletonShimmerModifier: ViewModifier {
    @State private var offsetX: CGFloat = -1.2
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func body(content: Content) -> some View {
        content
            .overlay {
                GeometryReader { proxy in
                    let width = max(proxy.size.width, 1)
                    LinearGradient(
                        stops: [
                            .init(color: .clear, location: 0.0),
                            .init(color: .white.opacity(0.12), location: 0.36),
                            .init(color: .white.opacity(0.26), location: 0.5),
                            .init(color: .white.opacity(0.12), location: 0.64),
                            .init(color: .clear, location: 1.0)
                        ],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                    .frame(width: width * 1.45)
                    .offset(x: width * offsetX)
                    .blendMode(.plusLighter)
                }
                .clipped()
            }
            .onAppear {
                guard offsetX == -1.2 else { return }
                guard !reduceMotion else {
                    offsetX = 0
                    return
                }
                withAnimation(.linear(duration: 1.15).repeatForever(autoreverses: false)) {
                    offsetX = 1.2
                }
            }
    }
}

extension View {
    func paxSkeletonShimmer(active: Bool = true) -> some View {
        Group {
            if active {
                self.modifier(PAXSkeletonShimmerModifier())
            } else {
                self
            }
        }
    }
}

struct PAXSkeletonBlock: View {
    var width: CGFloat? = nil
    var height: CGFloat = 10
    var cornerRadius: CGFloat = 999

    var body: some View {
        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
            .fill(PAXTheme.surfaceElevated)
            .frame(width: width, height: height)
            .overlay(
                RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
                    .stroke(PAXTheme.border.opacity(0.35), lineWidth: 1)
            )
            .paxSkeletonShimmer()
    }
}

struct PAXSkeletonCircle: View {
    var size: CGFloat = 40

    var body: some View {
        Circle()
            .fill(PAXTheme.surfaceElevated)
            .frame(width: size, height: size)
            .overlay(Circle().stroke(PAXTheme.border.opacity(0.4), lineWidth: 1))
            .paxSkeletonShimmer()
    }
}

struct PAXSkeletonListRow: View {
    var body: some View {
        HStack(spacing: 12) {
            PAXSkeletonCircle(size: 38)
            VStack(alignment: .leading, spacing: 7) {
                PAXSkeletonBlock(width: 140, height: 11, cornerRadius: 8)
                PAXSkeletonBlock(width: 190, height: 9, cornerRadius: 8)
                PAXSkeletonBlock(width: 96, height: 8, cornerRadius: 8)
            }
            Spacer(minLength: 0)
        }
        .padding(.vertical, 4)
    }
}

struct PAXSkeletonChatRow: View {
    var outgoing: Bool = false

    var body: some View {
        HStack(alignment: .top, spacing: 8) {
            if outgoing { Spacer(minLength: 30) } else { PAXSkeletonCircle(size: 28) }
            VStack(alignment: .leading, spacing: 6) {
                PAXSkeletonBlock(width: outgoing ? 170 : 134, height: 10, cornerRadius: 7)
                PAXSkeletonBlock(width: outgoing ? 210 : 186, height: 11, cornerRadius: 9)
                PAXSkeletonBlock(width: outgoing ? 96 : 80, height: 8, cornerRadius: 7)
            }
            if outgoing { PAXSkeletonCircle(size: 28) } else { Spacer(minLength: 30) }
        }
    }
}

private struct PAXLoaderPlayhead: View {
    @State private var xOffset: CGFloat = -1
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        GeometryReader { proxy in
            RoundedRectangle(cornerRadius: 999, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [PAXTheme.accent.opacity(0), PAXTheme.accent.opacity(0.85), PAXTheme.accent.opacity(0)],
                        startPoint: .top,
                        endPoint: .bottom
                    )
                )
                .frame(width: 2)
                .offset(x: max(proxy.size.width, 1) * xOffset)
                .onAppear {
                    guard xOffset == -1 else { return }
                    guard !reduceMotion else {
                        xOffset = 0.34
                        return
                    }
                    withAnimation(.linear(duration: 1.25).repeatForever(autoreverses: false)) {
                        xOffset = 1.05
                    }
                }
        }
    }
}

private struct PAXLoaderMeterFill: View {
    @State private var xOffset: CGFloat = -1
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        GeometryReader { proxy in
            RoundedRectangle(cornerRadius: 999, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [
                            PAXTheme.accent.opacity(0),
                            PAXTheme.accent.opacity(0.45),
                            PAXTheme.success.opacity(0.4),
                            PAXTheme.accent.opacity(0)
                        ],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                )
                .frame(width: max(proxy.size.width * 0.75, 36))
                .offset(x: proxy.size.width * xOffset)
                .onAppear {
                    guard xOffset == -1 else { return }
                    guard !reduceMotion else {
                        xOffset = 0
                        return
                    }
                    withAnimation(.linear(duration: 1.05).repeatForever(autoreverses: false)) {
                        xOffset = 1.0
                    }
                }
        }
    }
}

struct PAXTimelineLoaderCard: View {
    var status: String = "Lädt"

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 10) {
                PAXSkeletonBlock(width: 148, height: 11, cornerRadius: 999)
                Spacer(minLength: 0)
                Text(status)
                    .font(.caption2.weight(.semibold))
                    .foregroundStyle(PAXTheme.textSecondary)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(
                        Capsule()
                            .fill(PAXTheme.surfaceElevated.opacity(0.75))
                            .overlay(Capsule().stroke(PAXTheme.border.opacity(0.3), lineWidth: 1))
                    )
            }
            .padding(12)
            .background(
                LinearGradient(
                    colors: [PAXTheme.surface.opacity(0.78), PAXTheme.surface.opacity(0.45)],
                    startPoint: .top,
                    endPoint: .bottom
                )
            )

            VStack(spacing: 10) {
                ForEach(0..<2, id: \.self) { idx in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: 12, style: .continuous)
                            .fill(PAXTheme.surfaceElevated.opacity(idx == 0 ? 0.85 : 0.6))
                            .frame(height: 22)
                            .overlay(
                                RoundedRectangle(cornerRadius: 12, style: .continuous)
                                    .stroke(PAXTheme.border.opacity(0.35), lineWidth: 1)
                            )
                        HStack(spacing: 7) {
                            PAXSkeletonBlock(width: 62, height: 9, cornerRadius: 999)
                            PAXSkeletonBlock(width: 42, height: 9, cornerRadius: 999)
                            PAXSkeletonBlock(width: 88, height: 9, cornerRadius: 999)
                        }
                        .padding(.horizontal, 10)
                        PAXLoaderPlayhead()
                            .frame(height: 26)
                    }
                    .opacity(idx == 0 ? 1 : 0.82)
                }

                RoundedRectangle(cornerRadius: 999, style: .continuous)
                    .fill(PAXTheme.surfaceElevated.opacity(0.72))
                    .frame(height: 10)
                    .overlay(
                        RoundedRectangle(cornerRadius: 999, style: .continuous)
                            .stroke(PAXTheme.border.opacity(0.35), lineWidth: 1)
                    )
                    .overlay(PAXLoaderMeterFill().clipShape(RoundedRectangle(cornerRadius: 999, style: .continuous)))
            }
            .padding(12)
        }
        .background(
            RoundedRectangle(cornerRadius: 18, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.86))
                .overlay(RoundedRectangle(cornerRadius: 18, style: .continuous).stroke(PAXTheme.border.opacity(0.4), lineWidth: 1))
        )
    }
}

enum PAXShellLayout {
    static let tabBarBodyHeight: CGFloat = 56

    static var bottomSafeArea: CGFloat {
        UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap(\.windows)
            .first(where: \.isKeyWindow)?
            .safeAreaInsets.bottom ?? 0
    }

    static var tabBarReservedHeight: CGFloat {
        tabBarBodyHeight + max(bottomSafeArea, 6) + 10
    }
}

private struct ShellTabBarVisibleKey: EnvironmentKey {
    static let defaultValue = false
}

extension EnvironmentValues {
    var shellTabBarVisible: Bool {
        get { self[ShellTabBarVisibleKey.self] }
        set { self[ShellTabBarVisibleKey.self] = newValue }
    }
}

private struct ShellScrollClearanceModifier: ViewModifier {
    @Environment(\.shellTabBarVisible) private var tabBarVisible

    func body(content: Content) -> some View {
        content.padding(.bottom, tabBarVisible ? 6 : 0)
    }
}

extension View {
    func paxShellScrollClearance() -> some View {
        modifier(ShellScrollClearanceModifier())
    }
}
