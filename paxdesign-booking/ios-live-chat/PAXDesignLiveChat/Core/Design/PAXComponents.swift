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
            .paxRevolutSurface(cornerRadius: 16, elevation: 0)
    }
}

struct PAXField: View {
    let title: String
    let icon: String
    @Binding var text: String
    var isSecure = false
    var keyboardType: UIKeyboardType = .default

    var body: some View {
        PAXRevolutField(
            title: title,
            systemImage: icon,
            text: $text,
            isSecure: isSecure,
            keyboardType: keyboardType
        )
    }
}

struct PAXPrimaryButton: View {
    let title: String
    var isLoading = false
    let action: () -> Void

    var body: some View {
        PAXRevolutPrimaryButton(title: title, isLoading: isLoading, action: action)
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

struct PAXIconTapStyle: ButtonStyle {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed && !reduceMotion ? 0.9 : 1)
            .animation(reduceMotion ? nil : .spring(response: 0.2, dampingFraction: 0.62), value: configuration.isPressed)
    }
}

struct PAXSendButton: View {
    let isEnabled: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            ZStack {
                Circle()
                    .fill(isEnabled ? AnyShapeStyle(PAXBrandGradient.linear) : AnyShapeStyle(PAXTheme.surface3))
                PAXIcon("arrow.up.circle.fill", size: .card, tint: isEnabled ? PAXTheme.onAccent : PAXTheme.textTertiary)
            }
            .frame(width: 44, height: 44)
            .shadow(color: isEnabled ? PAXBrandGradient.glow : .clear, radius: 8, y: 4)
            .contentShape(Circle())
        }
        .buttonStyle(PAXRevolutPressableStyle())
        .disabled(!isEnabled)
        .accessibilityLabel(L10n.CommonSend)
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
                        colors: [PAXTheme.accent.opacity(0), PAXTheme.accent.opacity(0.55), PAXTheme.accent.opacity(0)],
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
            .background(PAXTheme.surface)

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
        .paxPremiumGlass(tier: .premium, cornerRadius: 18)
    }
}

struct PAXInlineLoader: View {
    var size: CGFloat = 20

    var body: some View {
        HStack(spacing: 4) {
            ForEach(0..<3, id: \.self) { index in
                Circle()
                    .fill(PAXTheme.accent.opacity(0.85))
                    .frame(width: size * 0.22, height: size * 0.22)
                    .opacity(0.35 + Double(index) * 0.2)
            }
        }
        .frame(width: size, height: size)
        .paxSkeletonShimmer()
    }
}

enum PAXSkeletonPreset {
    case list
    case dashboard
    case chatThread
    case deviceCards
}

struct PAXScreenLoadingStack: View {
    var status: String
    var rowCount: Int = 4
    var preset: PAXSkeletonPreset = .list

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            PAXTimelineLoaderCard(status: status)
            switch preset {
            case .list:
                ForEach(0..<rowCount, id: \.self) { _ in
                    PAXSkeletonListRow()
                        .paxCard(.list)
                }
            case .dashboard:
                HStack(spacing: 10) {
                    ForEach(0..<2, id: \.self) { _ in
                        VStack(alignment: .leading, spacing: 8) {
                            PAXSkeletonBlock(width: 72, height: 10, cornerRadius: 6)
                            PAXSkeletonBlock(width: 48, height: 22, cornerRadius: 8)
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(14)
                        .paxCard(.list)
                    }
                }
                ForEach(0..<max(1, rowCount - 1), id: \.self) { _ in
                    PAXSkeletonListRow()
                        .paxCard(.list)
                }
            case .chatThread:
                ForEach(0..<max(3, rowCount), id: \.self) { index in
                    PAXSkeletonChatRow(outgoing: index.isMultiple(of: 3))
                        .padding(.horizontal, 4)
                }
            case .deviceCards:
                ForEach(0..<rowCount, id: \.self) { _ in
                    HStack(spacing: 12) {
                        PAXSkeletonCircle(size: 34)
                        VStack(alignment: .leading, spacing: 7) {
                            PAXSkeletonBlock(width: 120, height: 11, cornerRadius: 8)
                            PAXSkeletonBlock(width: 180, height: 9, cornerRadius: 8)
                        }
                        Spacer(minLength: 0)
                        PAXSkeletonBlock(width: 54, height: 24, cornerRadius: 12)
                    }
                    .padding(.vertical, 6)
                    .paxCard(.list)
                }
            }
        }
    }
}

struct PAXChatThreadLoadingStack: View {
    var status: String = L10n.LoadingSessions
    var rowCount: Int = 5

    var body: some View {
        VStack(spacing: 14) {
            PAXTimelineLoaderCard(status: status)
            ForEach(0..<rowCount, id: \.self) { index in
                PAXSkeletonChatRow(outgoing: index.isMultiple(of: 3))
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .top)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
    }
}

enum PAXShellLayout {
    /// Standard UIKit tab bar icon row height.
    static let tabBarContentHeight: CGFloat = 49
    static let tabBarHairlineHeight: CGFloat = 0.33

    static var tabBarLayoutHeight: CGFloat {
        tabBarContentHeight + tabBarHairlineHeight
    }

    static var bottomSafeArea: CGFloat {
        UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap(\.windows)
            .first(where: \.isKeyWindow)?
            .safeAreaInsets.bottom ?? 0
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

/// Shell chrome uses an explicit VStack (content + tab bar). Overlay/safeAreaInset tab bars
/// do not reliably constrain nested NavigationStack / ScrollView children in ZStack shells.
private struct PAXShellBottomTabBarModifier: ViewModifier {
    let isVisible: Bool
    let items: [UiverseMenuBarItem]
    @Binding var selection: Int
    let reduceMotion: Bool
    @ObservedObject var scrollState: UiverseMenuScrollState

    func body(content: Content) -> some View {
        VStack(spacing: 0) {
            content
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .accessibilityIdentifier("pax.shell.content")
                .background {
                    if isVisible {
                        PAXShellScrollOffsetTracker(
                            scrollState: scrollState,
                            reduceMotion: reduceMotion
                        )
                    }
                }

            if isVisible {
                UiverseMenuBarView(
                    items: items,
                    selection: $selection,
                    reduceMotion: reduceMotion
                )
                .accessibilityIdentifier("pax.shell.tabBar")
                .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
    }
}

extension View {
    func paxShellBottomTabBar(
        isVisible: Bool,
        items: [UiverseMenuBarItem],
        selection: Binding<Int>,
        reduceMotion: Bool,
        scrollState: UiverseMenuScrollState
    ) -> some View {
        modifier(
            PAXShellBottomTabBarModifier(
                isVisible: isVisible,
                items: items,
                selection: selection,
                reduceMotion: reduceMotion,
                scrollState: scrollState
            )
        )
    }

    /// Replaces the system pull-to-refresh spinner with the premium skeleton loading UI.
    func paxPremiumRefreshable(
        status: String,
        rowCount: Int = 4,
        action: @escaping () async -> Void
    ) -> some View {
        modifier(PAXPremiumRefreshModifier(status: status, rowCount: rowCount, action: action))
    }
}

// MARK: - Premium Pull-to-Refresh

private struct PAXPremiumRefreshModifier: ViewModifier {
    let status: String
    let rowCount: Int
    let action: () async -> Void

    func body(content: Content) -> some View {
        PAXPremiumRefreshContainer(status: status, rowCount: rowCount, action: action) {
            content
        }
    }
}

private struct PAXPremiumRefreshContainer<Content: View>: View {
    let status: String
    let rowCount: Int
    let action: () async -> Void
    @ViewBuilder let content: () -> Content

    @State private var isRefreshing = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        Group {
            if isRefreshing {
                refreshLoadingBody
            } else {
                content()
            }
        }
        .animation(reduceMotion ? nil : .easeInOut(duration: 0.28), value: isRefreshing)
        .refreshable {
            guard !isRefreshing else { return }
            setRefreshing(true)
            await action()
            setRefreshing(false)
        }
        .background {
            PAXRefreshControlSpinnerHider()
        }
    }

    private var refreshLoadingBody: some View {
        ScrollView {
            PAXScreenLoadingStack(status: status, rowCount: rowCount)
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
        }
        .scrollIndicators(.hidden)
        .paxScreenBackground()
        .transition(.opacity.combined(with: .scale(scale: 0.985)))
    }

    private func setRefreshing(_ value: Bool) {
        if reduceMotion {
            isRefreshing = value
        } else {
            withAnimation(.easeInOut(duration: 0.28)) {
                isRefreshing = value
            }
        }
    }
}

private struct PAXRefreshControlSpinnerHider: UIViewRepresentable {
    func makeUIView(context: Context) -> PAXRefreshAnchorView {
        let view = PAXRefreshAnchorView()
        view.isUserInteractionEnabled = false
        view.backgroundColor = .clear
        view.onHierarchyChange = { [weak view] in
            guard let view else { return }
            PAXRefreshControlCustomizer.hideSystemSpinner(from: view)
        }
        return view
    }

    func updateUIView(_ uiView: PAXRefreshAnchorView, context: Context) {
        PAXRefreshControlCustomizer.hideSystemSpinner(from: uiView)
    }
}

private final class PAXRefreshAnchorView: UIView {
    var onHierarchyChange: (() -> Void)?

    override func didMoveToWindow() {
        super.didMoveToWindow()
        onHierarchyChange?()
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        onHierarchyChange?()
    }
}

private enum PAXRefreshControlCustomizer {
    static func hideSystemSpinner(from anchor: UIView) {
        guard let scrollView = nearestScrollView(from: anchor) else { return }
        scrollView.refreshControl?.tintColor = .clear
        scrollView.refreshControl?.backgroundColor = .clear
        scrollView.refreshControl?.subviews.forEach { subview in
            subview.alpha = 0
            subview.isHidden = true
        }
    }

    fileprivate static func nearestScrollView(from view: UIView) -> UIScrollView? {
        var current: UIView? = view
        while let node = current {
            if let scroll = node as? UIScrollView { return scroll }
            for subview in node.subviews {
                if let scroll = findScrollView(in: subview) { return scroll }
            }
            current = node.superview
        }
        return findScrollView(in: view)
    }

    private static func findScrollView(in view: UIView) -> UIScrollView? {
        if let scroll = view as? UIScrollView { return scroll }
        for subview in view.subviews {
            if let scroll = findScrollView(in: subview) { return scroll }
        }
        return nil
    }
}

// MARK: - Bottom menu scroll shrink tracking

private struct PAXShellScrollOffsetTracker: UIViewRepresentable {
    @ObservedObject var scrollState: UiverseMenuScrollState
    let reduceMotion: Bool

    func makeCoordinator() -> Coordinator {
        Coordinator(scrollState: scrollState, reduceMotion: reduceMotion)
    }

    func makeUIView(context: Context) -> PAXShellScrollTrackerAnchorView {
        let view = PAXShellScrollTrackerAnchorView()
        view.isUserInteractionEnabled = false
        view.backgroundColor = .clear
        view.onHierarchyChange = { [weak view] in
            guard let view else { return }
            context.coordinator.attach(to: view)
        }
        return view
    }

    func updateUIView(_ uiView: PAXShellScrollTrackerAnchorView, context: Context) {
        context.coordinator.reduceMotion = reduceMotion
        context.coordinator.attach(to: uiView)
    }

    final class Coordinator: NSObject {
        let scrollState: UiverseMenuScrollState
        var reduceMotion: Bool
        private weak var observedScrollView: UIScrollView?
        private var offsetObservation: NSKeyValueObservation?

        init(scrollState: UiverseMenuScrollState, reduceMotion: Bool) {
            self.scrollState = scrollState
            self.reduceMotion = reduceMotion
        }

        func attach(to anchor: UIView) {
            guard let scrollView = PAXRefreshControlCustomizer.nearestScrollView(from: anchor),
                  scrollView !== observedScrollView else { return }
            offsetObservation?.invalidate()
            observedScrollView = scrollView
            offsetObservation = scrollView.observe(\.contentOffset, options: [.new]) { [weak self] (scrollView: UIScrollView, _: NSKeyValueObservedChange<CGPoint>) in
                guard let self else { return }
                let offset = max(0, scrollView.contentOffset.y + scrollView.adjustedContentInset.top)
                self.scrollState.ingestScrollOffset(offset, reduceMotion: self.reduceMotion)
            }
        }

        deinit {
            offsetObservation?.invalidate()
        }
    }
}

private final class PAXShellScrollTrackerAnchorView: UIView {
    var onHierarchyChange: (() -> Void)?

    override func didMoveToWindow() {
        super.didMoveToWindow()
        onHierarchyChange?()
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        onHierarchyChange?()
    }
}
