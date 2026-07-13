import SwiftUI

struct PAXRingMetric: Identifiable, Equatable {
    let id = UUID()
    let label: String
    let value: Int
    var tint: Color = PAXTheme.accent
}

struct PAXSevenDayAnalyticsRings: View {
    let title: String
    let items: [PAXRingMetric]

    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var animateChart = false

    private var maxValue: Int {
        max(items.map(\.value).max() ?? 0, 1)
    }

    private var totalValue: Int {
        items.map(\.value).reduce(0, +)
    }

    private var averageValue: Double {
        guard !items.isEmpty else { return 0 }
        return Double(totalValue) / Double(items.count)
    }

    private var peakItem: PAXRingMetric? {
        items.max(by: { $0.value < $1.value })
    }

    private var trendDelta: Double? {
        guard items.count >= 4 else { return nil }
        let midpoint = items.count / 2
        let earlier = items.prefix(midpoint).map(\.value).reduce(0, +)
        let recent = items.suffix(items.count - midpoint).map(\.value).reduce(0, +)
        let earlierAvg = Double(earlier) / Double(midpoint)
        let recentAvg = Double(recent) / Double(items.count - midpoint)
        guard earlierAvg > 0 else { return recentAvg > 0 ? 100 : nil }
        return ((recentAvg - earlierAvg) / earlierAvg) * 100
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            header

            chartPanel

            footerStats
        }
        .padding(16)
        .paxCard(.standard)
        .onAppear { startChartAnimation() }
        .onChange(of: items) { _ in
            animateChart = false
            startChartAnimation()
        }
    }

    private var header: some View {
        HStack(alignment: .top, spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.headline.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)

                Text(L10n.AnalyticsSevenDay)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Spacer(minLength: 8)

            ZStack {
                Circle()
                    .stroke(PAXTheme.accent.opacity(0.14), lineWidth: 4)
                Circle()
                    .trim(from: 0, to: animateChart ? 1 : 0)
                    .stroke(
                        PAXTheme.accent,
                        style: StrokeStyle(lineWidth: 4, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))

                VStack(spacing: 0) {
                    Text("\(totalValue)")
                        .font(.caption.weight(.bold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(L10n.AnalyticsRingTotal)
                        .font(.system(size: 8, weight: .medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
            .frame(width: 52, height: 52)
        }
    }

    private var chartPanel: some View {
        HStack(spacing: 6) {
            ForEach(Array(items.enumerated()), id: \.element.id) { index, item in
                PAXAnalyticsDayRing(
                    item: item,
                    maxValue: maxValue,
                    isPeak: item.id == peakItem?.id && item.value > 0,
                    isLatest: index == items.count - 1,
                    animate: animateChart,
                    delay: Double(index) * 0.05
                )
                .frame(maxWidth: .infinity)
            }
        }
        .padding(.horizontal, 6)
        .padding(.vertical, 14)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [
                            PAXTheme.surface.opacity(colorScheme == .dark ? 0.38 : 0.5),
                            PAXTheme.surface.opacity(colorScheme == .dark ? 0.22 : 0.32),
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.2), lineWidth: 0.5)
                )
        )
    }

    private var footerStats: some View {
        HStack(spacing: 10) {
            statPill(
                title: L10n.AnalyticsPeak,
                value: "\(peakItem?.value ?? 0)",
                tint: PAXTheme.accent
            )

            statPill(
                title: L10n.AnalyticsAvgPerDay,
                value: String(format: "%.1f", averageValue),
                tint: PAXTheme.textSecondary
            )

            if let trendDelta {
                trendPill(delta: trendDelta)
            }

            Spacer(minLength: 0)
        }
    }

    private func statPill(title: String, value: String, tint: Color) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(value)
                .font(.caption.weight(.semibold))
                .foregroundStyle(tint == PAXTheme.textSecondary ? PAXTheme.textPrimary : tint)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 7)
        .background(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.45))
        )
    }

    private func trendPill(delta: Double) -> some View {
        let positive = delta >= 0
        return HStack(spacing: 4) {
            PAXIcon(positive ? "arrow.up.right" : "arrow.down.right", size: .inline)
            Text(String(format: "%+.0f%%", delta))
                .font(.caption.weight(.semibold))
        }
        .foregroundStyle(PAXTheme.textPrimary)
        .padding(.horizontal, 10)
        .padding(.vertical, 7)
        .background(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .fill(PAXTheme.surface.opacity(0.45))
        )
    }

    private func startChartAnimation() {
        guard !reduceMotion else {
            animateChart = true
            return
        }
        withAnimation(.spring(response: 0.68, dampingFraction: 0.84)) {
            animateChart = true
        }
    }
}

private struct PAXAnalyticsDayRing: View {
    let item: PAXRingMetric
    let maxValue: Int
    let isPeak: Bool
    let isLatest: Bool
    let animate: Bool
    let delay: Double

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private var progress: CGFloat {
        guard maxValue > 0, item.value > 0 else { return 0 }
        return max(0.08, CGFloat(item.value) / CGFloat(maxValue))
    }

    private var ringTint: Color {
        isPeak ? item.tint : item.tint.opacity(isLatest ? 0.95 : 0.72)
    }

    private var shortDayLabel: String {
        let trimmed = item.label.trimmingCharacters(in: .whitespacesAndNewlines)
        if trimmed.count <= 3 { return trimmed }
        return String(trimmed.prefix(3))
    }

    var body: some View {
        VStack(spacing: 6) {
            ZStack {
                Circle()
                    .stroke(ringTint.opacity(0.14), lineWidth: 4.5)

                Circle()
                    .trim(from: 0, to: animate ? progress : 0)
                    .stroke(
                        AngularGradient(
                            colors: [ringTint.opacity(0.55), ringTint, ringTint.opacity(0.85)],
                            center: .center
                        ),
                        style: StrokeStyle(lineWidth: 4.5, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))
                    .shadow(color: isPeak ? ringTint.opacity(0.22) : .clear, radius: 3, x: 0, y: 1)

                Text(item.value > 0 ? "\(item.value)" : "·")
                    .font(.system(size: 10, weight: .bold, design: .rounded))
                    .foregroundStyle(item.value > 0 ? PAXTheme.textPrimary : PAXTheme.textTertiary)
            }
            .frame(width: 40, height: 40)
            .animation(
                reduceMotion ? nil : .spring(response: 0.68, dampingFraction: 0.84).delay(delay),
                value: animate
            )

            Text(shortDayLabel)
                .font(.system(size: 10, weight: isLatest ? .bold : .medium))
                .foregroundStyle(isLatest ? PAXTheme.accent : PAXTheme.textTertiary)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(item.label): \(item.value)")
    }
}

struct PAXSessionMixRings: View {
    let title: String
    let slices: [PAXRingMetric]

    @State private var animate = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private var total: Int {
        max(slices.map(\.value).reduce(0, +), 1)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text(title)
                .font(.headline)

            HStack(spacing: 18) {
                ZStack {
                    ForEach(Array(slices.enumerated()), id: \.element.id) { index, slice in
                        let start = slices.prefix(index).map(\.value).reduce(0, +)
                        let fraction = Double(slice.value) / Double(total)
                        let startFraction = Double(start) / Double(total)
                        Circle()
                            .trim(
                                from: animate ? startFraction : 0,
                                to: animate ? startFraction + fraction : 0
                            )
                            .stroke(
                                slice.tint,
                                style: StrokeStyle(lineWidth: 9, lineCap: .round)
                            )
                            .rotationEffect(.degrees(-90))
                            .padding(8)
                    }

                    VStack(spacing: 2) {
                        Text("\(total)")
                            .font(.title3.weight(.bold))
                        Text(L10n.AnalyticsTotal)
                            .font(.caption2)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                .frame(width: 92, height: 92)

                VStack(alignment: .leading, spacing: 8) {
                    ForEach(slices) { slice in
                        HStack(spacing: 8) {
                            Circle()
                                .fill(slice.tint)
                                .frame(width: 7, height: 7)
                            Text(slice.label)
                                .font(.caption)
                            Spacer(minLength: 0)
                            Text("\(slice.value)")
                                .font(.caption.weight(.semibold))
                        }
                    }
                }
            }
        }
        .padding(16)
        .paxCard(.standard)
        .onAppear {
            guard !reduceMotion else {
                animate = true
                return
            }
            withAnimation(.spring(response: 0.8, dampingFraction: 0.84)) {
                animate = true
            }
        }
    }
}
