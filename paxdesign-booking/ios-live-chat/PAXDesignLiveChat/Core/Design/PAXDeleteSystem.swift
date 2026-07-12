import SwiftUI

@MainActor
final class PAXDeletePresenter: ObservableObject {
    static let shared = PAXDeletePresenter()

    struct Request: Identifiable {
        let id = UUID()
        let title: String
        let message: String
        let itemTitle: String?
        let confirmTitle: String
        let onConfirm: () -> Void
    }

    @Published private(set) var activeRequest: Request?
    @Published private(set) var isAnimating = false

    private init() {}

    func request(
        title: String = L10n.CommonDelete,
        message: String,
        itemTitle: String? = nil,
        confirmTitle: String = L10n.CommonDelete,
        onConfirm: @escaping () -> Void
    ) {
        guard activeRequest == nil else { return }
        activeRequest = Request(
            title: title,
            message: message,
            itemTitle: itemTitle,
            confirmTitle: confirmTitle,
            onConfirm: onConfirm
        )
    }

    func cancel() {
        guard !isAnimating else { return }
        activeRequest = nil
    }

    func confirm() {
        guard let request = activeRequest, !isAnimating else { return }
        isAnimating = true
        PAXHaptics.warning()
    }

    func finishAnimation() {
        guard let request = activeRequest else { return }
        let action = request.onConfirm
        activeRequest = nil
        isAnimating = false
        action()
        PAXHaptics.medium()
    }
}

struct PAXDeleteOverlay: View {
    @ObservedObject private var presenter = PAXDeletePresenter.shared
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        if let request = presenter.activeRequest {
            ZStack {
                Color.black.opacity(0.52)
                    .ignoresSafeArea()
                    .onTapGesture {
                        if !presenter.isAnimating { presenter.cancel() }
                    }

                PAXDeleteDialogCard(
                    request: request,
                    isAnimating: presenter.isAnimating,
                    reduceMotion: reduceMotion,
                    onCancel: { presenter.cancel() },
                    onConfirm: { presenter.confirm() },
                    onAnimationComplete: { presenter.finishAnimation() }
                )
                .padding(.horizontal, 22)
                .transition(.scale(scale: 0.94).combined(with: .opacity))
            }
            .zIndex(200)
            .animation(.spring(response: 0.34, dampingFraction: 0.86), value: presenter.activeRequest?.id)
        }
    }
}

private struct PAXDeleteDialogCard: View {
    let request: PAXDeletePresenter.Request
    let isAnimating: Bool
    let reduceMotion: Bool
    let onCancel: () -> Void
    let onConfirm: () -> Void
    let onAnimationComplete: () -> Void

    @State private var avatarOffsetY: CGFloat = 0
    @State private var linesOffsetX: CGFloat = 0
    @State private var showShards = false
    @State private var shardProgress: CGFloat = 0
    @State private var rowCollapsed = false
    @State private var cardBackgroundOpacity: Double = 1

    var body: some View {
        VStack(spacing: 16) {
            Text(request.title)
                .font(.headline)
                .foregroundStyle(PAXTheme.textPrimary)

            Text(request.message)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
                .fixedSize(horizontal: false, vertical: true)

            deletePreview
                .frame(height: rowCollapsed ? 0 : 96)
                .clipped()
                .padding(.vertical, rowCollapsed ? 0 : 4)

            if !isAnimating {
                HStack(spacing: 10) {
                    Button(L10n.CommonCancel, action: onCancel)
                        .buttonStyle(.bordered)
                        .frame(maxWidth: .infinity)

                    Button(request.confirmTitle, role: .destructive, action: onConfirm)
                        .buttonStyle(.borderedProminent)
                        .tint(PAXTheme.danger)
                        .frame(maxWidth: .infinity)
                }
            }
        }
        .padding(18)
        .paxPremiumGlass(tier: .premium, cornerRadius: 20, accent: PAXTheme.danger)
        .onChange(of: isAnimating) { animating in
            guard animating else { return }
            runShatterSequence()
        }
    }

    private var deletePreview: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(PAXTheme.surface.opacity(cardBackgroundOpacity))
                .overlay(
                    RoundedRectangle(cornerRadius: 10, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.35), lineWidth: 1)
                )

            HStack(spacing: 14) {
                RoundedRectangle(cornerRadius: 8, style: .continuous)
                    .fill(PAXTheme.surfaceElevated)
                    .frame(width: 56, height: 56)
                    .overlay(
                        PAXIcon( "person.fill")
                            .foregroundStyle(PAXTheme.textTertiary)
                    )
                    .offset(y: avatarOffsetY)

                VStack(alignment: .leading, spacing: 8) {
                    RoundedRectangle(cornerRadius: 4, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                        .frame(height: 10)
                    RoundedRectangle(cornerRadius: 4, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                        .frame(height: 10)
                    RoundedRectangle(cornerRadius: 4, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                        .frame(width: 120, height: 10)
                }
                .offset(x: linesOffsetX)

                Spacer(minLength: 0)
            }
            .padding(16)
            .opacity(showShards ? 0 : 1)

            if showShards {
                PAXShatterShardsView(progress: shardProgress)
                    .allowsHitTesting(false)
            }
        }
        .overlay(alignment: .topLeading) {
            if let itemTitle = request.itemTitle, !itemTitle.isEmpty, !isAnimating {
                Text(itemTitle)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textSecondary)
                    .padding(.horizontal, 10)
                    .padding(.top, 8)
            }
        }
    }

    private func runShatterSequence() {
        if reduceMotion {
            rowCollapsed = true
            onAnimationComplete()
            return
        }

        withAnimation(.easeInOut(duration: 0.5)) {
            avatarOffsetY = 90
        }

        withAnimation(.easeInOut(duration: 0.5).delay(0.05)) {
            linesOffsetX = 320
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + 0.55) {
            cardBackgroundOpacity = 0
            showShards = true
            withAnimation(.easeIn(duration: 0.55)) {
                shardProgress = 1
            }
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + 0.95) {
            withAnimation(.easeInOut(duration: 0.45)) {
                rowCollapsed = true
            }
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + 1.35) {
            onAnimationComplete()
        }
    }
}

private struct PAXShatterShardsView: View {
    let progress: CGFloat

    private let shards: [PAXShatterShard] = PAXShatterShard.library

    var body: some View {
        GeometryReader { proxy in
            let scaleX = proxy.size.width / 500
            let scaleY = proxy.size.height / 124

            ZStack {
                ForEach(Array(shards.enumerated()), id: \.offset) { index, shard in
                    shard.path
                        .fill(PAXTheme.surface.opacity(0.95))
                        .scaleEffect(x: scaleX, y: scaleY, anchor: .topLeading)
                        .scaleEffect(1 - progress * shard.scaleSpeed)
                        .rotationEffect(.degrees(progress * shard.rotation))
                        .offset(
                            x: progress * shard.driftX * scaleX,
                            y: progress * shard.driftY * scaleY
                        )
                        .opacity(Double(1 - progress * 0.85))
                        .animation(
                            .easeIn(duration: 0.45 + shard.delay * 0.2).delay(shard.delay),
                            value: progress
                        )
                }
            }
        }
    }
}

private struct PAXShatterShard {
    let path: Path
    let rotation: Double
    let scaleSpeed: CGFloat
    let driftX: CGFloat
    let driftY: CGFloat
    let delay: Double

    static let library: [PAXShatterShard] = {
        let specs: [(String, Double, CGFloat, CGFloat, CGFloat, Double)] = [
            ("M-1,-1 L71,39 L162,-40 Z", -18, 1.0, -12, 28, 0.02),
            ("M287,-1 L47,46 L162,-48 Z", 14, 1.05, 18, 22, 0.04),
            ("M282,0 L59,33 L-2,70 Z", -8, 0.95, -22, 16, 0.06),
            ("M159,29 L0,129 L-2,70 Z", 22, 1.1, -16, 34, 0.03),
            ("M159,29 L-6,128 L80,125 Z", -12, 0.92, 8, 26, 0.05),
            ("M159,29 L230,84 L170,125 Z", 16, 1.08, 24, 18, 0.07),
            ("M159,29 L232,86 L226,12 Z", -20, 0.98, 14, -8, 0.08),
            ("M299,126 L228,83 L287,-1 Z", 10, 1.02, -10, -14, 0.09),
            ("M298,124 L394,124 L339,59 Z", -6, 0.96, 20, -6, 0.1),
            ("M391,1 L318,37 L287,-1 Z", 18, 1.04, -8, -12, 0.11),
            ("M391,1 L353,81 L391,127 Z", -14, 0.94, 12, 20, 0.12),
            ("M391,1 L466,0 L442,42 Z", 8, 1.06, 26, -4, 0.13),
            ("M442,126 L438,42 L389,128 Z", -16, 0.97, -18, 10, 0.14),
            ("M465,0 L439,42 L503,-1 Z", 12, 1.03, 22, -10, 0.15),
            ("M502,123 L437,41 L500,57 Z", -10, 0.99, 16, 8, 0.16),
            ("M505,124 L437,40 L438,125 Z", 6, 1.01, -14, 14, 0.17)
        ]

        return specs.map { spec in
            PAXShatterShard(
                path: Path { path in
                    parseSimplePolygon(spec.0, into: &path)
                },
                rotation: spec.1,
                scaleSpeed: spec.2,
                driftX: spec.3,
                driftY: spec.4,
                delay: spec.5
            )
        }
    }()
}

private func parseSimplePolygon(_ spec: String, into path: inout Path) {
    let tokens = spec
        .replacingOccurrences(of: "M", with: "")
        .replacingOccurrences(of: "L", with: " ")
        .replacingOccurrences(of: "Z", with: "")
        .split(separator: " ")
        .compactMap { Double($0) }

    guard tokens.count >= 4 else { return }
    path.move(to: CGPoint(x: tokens[0], y: tokens[1]))
    var index = 2
    while index + 1 < tokens.count {
        path.addLine(to: CGPoint(x: tokens[index], y: tokens[index + 1]))
        index += 2
    }
    path.closeSubpath()
}

enum PAXDelete {
    @MainActor
    static func confirm(
        title: String = L10n.CommonDelete,
        message: String,
        itemTitle: String? = nil,
        confirmTitle: String = L10n.CommonDelete,
        perform: @escaping () -> Void
    ) {
        PAXDeletePresenter.shared.request(
            title: title,
            message: message,
            itemTitle: itemTitle,
            confirmTitle: confirmTitle,
            onConfirm: perform
        )
    }
}
