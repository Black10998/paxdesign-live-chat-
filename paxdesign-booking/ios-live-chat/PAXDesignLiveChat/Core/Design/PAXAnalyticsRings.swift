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

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var animateRings = false

    private var maxValue: Int {
        max(items.map(\.value).max() ?? 0, 1)
    }

    private var totalValue: Int {
        items.map(\.value).reduce(0, +)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .firstTextBaseline) {
                Text(title)
                    .font(.headline)
                    .foregroundStyle(PAXTheme.textPrimary)
                Spacer(minLength: 8)
                Text("\(totalValue) total")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textSecondary)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(
                        Capsule()
                            .fill(PAXTheme.surface.opacity(0.55))
                            .overlay(Capsule().stroke(PAXTheme.border.opacity(0.28), lineWidth: 0.5))
                    )
            }

            HStack(spacing: 6) {
                ForEach(Array(items.enumerated()), id: \.element.id) { index, item in
                    PAXActivityRingCell(
                        item: item,
                        maxValue: maxValue,
                        animate: animateRings,
                        delay: Double(index) * 0.05
                    )
                    .frame(maxWidth: .infinity)
                }
            }
        }
        .padding(16)
        .paxCard(.standard)
        .onAppear {
            guard !reduceMotion else {
                animateRings = true
                return
            }
            withAnimation(.spring(response: 0.72, dampingFraction: 0.82)) {
                animateRings = true
            }
        }
        .onChange(of: items) { _ in
            animateRings = false
            guard !reduceMotion else {
                animateRings = true
                return
            }
            withAnimation(.spring(response: 0.72, dampingFraction: 0.82)) {
                animateRings = true
            }
        }
    }
}

private struct PAXActivityRingCell: View {
    let item: PAXRingMetric
    let maxValue: Int
    let animate: Bool
    let delay: Double

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private var targetProgress: Double {
        guard maxValue > 0 else { return 0 }
        return min(Double(item.value) / Double(maxValue), 1)
    }

    private var displayedProgress: Double {
        animate ? targetProgress : 0
    }

    var body: some View {
        VStack(spacing: 7) {
            ZStack {
                Circle()
                    .stroke(PAXTheme.border.opacity(0.22), lineWidth: 3.5)

                Circle()
                    .trim(from: 0, to: displayedProgress)
                    .stroke(
                        AngularGradient(
                            colors: [
                                item.tint.opacity(0.35),
                                item.tint,
                                item.tint.opacity(0.75),
                                item.tint.opacity(0.35)
                            ],
                            center: .center
                        ),
                        style: StrokeStyle(lineWidth: 3.5, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))
                    .shadow(color: item.tint.opacity(0.18), radius: 3, x: 0, y: 1)

                Circle()
                    .fill(
                        RadialGradient(
                            colors: [item.tint.opacity(0.12), .clear],
                            center: .center,
                            startRadius: 0,
                            endRadius: 18
                        )
                    )
                    .frame(width: 30, height: 30)

                Text("\(item.value)")
                    .font(.system(size: 10, weight: .bold, design: .rounded))
                    .foregroundStyle(PAXTheme.textPrimary)
                    .minimumScaleFactor(0.7)
            }
            .frame(width: 40, height: 40)
            .animation(
                reduceMotion ? nil : .spring(response: 0.72, dampingFraction: 0.82).delay(delay),
                value: displayedProgress
            )

            Text(item.label)
                .font(.system(size: 9, weight: .semibold))
                .foregroundStyle(PAXTheme.textSecondary)
                .lineLimit(1)
                .minimumScaleFactor(0.75)
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
                        Text("Total")
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
