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

enum PAXActivityColorScale {
    static func tint(for value: Int, average: Double, accent: Color) -> Color {
        guard value > 0 else { return Color.gray.opacity(0.35) }
        let ratio = Double(value) / max(average, 1)
        if ratio >= 1.25 { return accent }
        if ratio >= 0.85 { return Color(red: 0.20, green: 0.78, blue: 0.45) }
        if ratio >= 0.45 { return Color(red: 1.0, green: 0.62, blue: 0.12) }
        return Color(red: 0.95, green: 0.38, blue: 0.34).opacity(0.88)
    }

    static func label(for value: Int, average: Double) -> String {
        guard value > 0 else { return "—" }
        let ratio = Double(value) / max(average, 1)
        if ratio >= 1.25 { return "★" }
        if ratio >= 0.85 { return "↑" }
        if ratio >= 0.45 { return "→" }
        return "↓"
    }
}

struct PAXProfessionalAnalyticsDashboard: View {
    let title: String
    let days: [PlatformActivityDay]
    let trends: PlatformDashboardTrends
    let categories: [PlatformReportSlice]

    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var animateChart = false
    @State private var selectedDayIndex: Int?

    private var totalMessages: Int { days.map(\.messages).reduce(0, +) }
    private var totalSessions: Int { days.map(\.sessions).reduce(0, +) }

    private var messageAverage: Double {
        guard !days.isEmpty else { return 0 }
        return Double(totalMessages) / Double(days.count)
    }

    private var peakMessages: Int {
        days.map(\.messages).max() ?? 0
    }

    private var peakDay: PlatformActivityDay? {
        days.max(by: { $0.messages < $1.messages })
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            heroHeader
            chartCanvas
            dayProgressRow
            summaryStrip
        }
        .padding(20)
        .paxPremiumGlass(tier: .premium, cornerRadius: 22)
        .onAppear { startAnimation() }
        .onChange(of: days) { _ in
            animateChart = false
            selectedDayIndex = nil
            startAnimation()
        }
    }

    private var heroHeader: some View {
        HStack(alignment: .top, spacing: 16) {
            VStack(alignment: .leading, spacing: 6) {
                Text(title)
                    .font(.title3.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(L10n.AnalyticsSevenDay)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Spacer(minLength: 8)

            VStack(alignment: .trailing, spacing: 2) {
                Text("\(totalMessages)")
                    .font(.system(size: 34, weight: .bold, design: .rounded))
                    .foregroundStyle(PAXTheme.textPrimary)
                    .contentTransition(.numericText())
                Text(L10n.AnalyticsMessages)
                    .font(.caption.weight(.medium))
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
    }

    private var chartCanvas: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 16) {
                legendItem(color: chartPrimary, label: L10n.AnalyticsMessages)
                legendItem(color: chartSecondary, label: L10n.AnalyticsSessions, dashed: true)
            }
            .font(.caption2.weight(.medium))

            GeometryReader { proxy in
                let size = CGSize(width: proxy.size.width, height: proxy.size.height)
                let maxY = max(CGFloat(peakMessages), CGFloat(days.map(\.sessions).max() ?? 0), 1)

                ZStack(alignment: .bottom) {
                    premiumGrid(in: size)

                    areaFill(
                        values: days.map { CGFloat($0.messages) },
                        maxY: maxY,
                        size: size,
                        base: chartPrimary.opacity(colorScheme == .dark ? 0.28 : 0.18),
                        peak: chartPrimary.opacity(colorScheme == .dark ? 0.06 : 0.04)
                    )

                    chartLine(
                        values: days.map { CGFloat($0.messages) },
                        maxY: maxY,
                        size: size,
                        color: chartPrimary,
                        animate: animateChart,
                        lineWidth: 2.5
                    )

                    chartLine(
                        values: days.map { CGFloat($0.sessions) },
                        maxY: maxY,
                        size: size,
                        color: chartSecondary,
                        animate: animateChart,
                        dashed: true,
                        lineWidth: 1.75
                    )

                    dayMarkers(maxY: maxY, size: size)
                }
            }
            .frame(height: 148)

            if let index = selectedDayIndex, days.indices.contains(index) {
                selectedDayDetail(days[index])
                    .transition(.opacity.combined(with: .move(edge: .top)))
            }
        }
        .padding(14)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(
                    LinearGradient(
                        colors: [
                            PAXTheme.surface.opacity(colorScheme == .dark ? 0.34 : 0.52),
                            PAXTheme.surface.opacity(colorScheme == .dark ? 0.16 : 0.28),
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(PAXTheme.border.opacity(colorScheme == .dark ? 0.14 : 0.18), lineWidth: 0.5)
                )
        )
    }

    private var dayProgressRow: some View {
        HStack(spacing: 8) {
            ForEach(Array(days.enumerated()), id: \.element.label) { index, day in
                dayColumn(day, index: index)
            }
        }
    }

    private var summaryStrip: some View {
        HStack(spacing: 10) {
            metricCapsule(
                title: L10n.AnalyticsPeak,
                value: "\(peakDay?.messages ?? 0)",
                subtitle: peakDay.map { shortDayLabel($0.label) } ?? "—"
            )
            metricCapsule(
                title: L10n.AnalyticsAvgPerDay,
                value: String(format: "%.1f", messageAverage),
                subtitle: L10n.AnalyticsMessages
            )
            if !categories.isEmpty {
                metricCapsule(
                    title: L10n.AnalyticsSessions,
                    value: "\(totalSessions)",
                    subtitle: L10n.AnalyticsRingTotal
                )
            }
        }
    }

    private var chartPrimary: Color {
        PAXTheme.accent.opacity(colorScheme == .dark ? 0.92 : 0.88)
    }

    private var chartSecondary: Color {
        PAXTheme.textSecondary.opacity(colorScheme == .dark ? 0.72 : 0.58)
    }

    private func legendItem(color: Color, label: String, dashed: Bool = false) -> some View {
        HStack(spacing: 6) {
            if dashed {
                Capsule()
                    .stroke(color, style: StrokeStyle(lineWidth: 1.5, dash: [3, 3]))
                    .frame(width: 14, height: 2)
            } else {
                Capsule()
                    .fill(color)
                    .frame(width: 14, height: 3)
            }
            Text(label)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private func dayColumn(_ day: PlatformActivityDay, index: Int) -> some View {
        let progress = peakMessages > 0 ? CGFloat(day.messages) / CGFloat(peakMessages) : 0
        let isSelected = selectedDayIndex == index
        let isLatest = index == days.count - 1

        return Button {
            withAnimation(reduceMotion ? nil : .spring(response: 0.38, dampingFraction: 0.86)) {
                selectedDayIndex = selectedDayIndex == index ? nil : index
            }
            PAXHaptics.light()
        } label: {
            VStack(spacing: 8) {
                ZStack(alignment: .bottom) {
                    RoundedRectangle(cornerRadius: 6, style: .continuous)
                        .fill(PAXTheme.border.opacity(colorScheme == .dark ? 0.18 : 0.12))
                        .frame(height: 44)

                    RoundedRectangle(cornerRadius: 6, style: .continuous)
                        .fill(
                            LinearGradient(
                                colors: [
                                    chartPrimary.opacity(isSelected ? 0.95 : 0.72),
                                    chartPrimary.opacity(isSelected ? 0.55 : 0.28),
                                ],
                                startPoint: .top,
                                endPoint: .bottom
                            )
                        )
                        .frame(height: max(4, 44 * (animateChart ? progress : 0)))
                        .animation(
                            reduceMotion ? nil : .spring(response: 0.62, dampingFraction: 0.84).delay(Double(index) * 0.04),
                            value: animateChart
                        )
                }
                .frame(maxWidth: .infinity)

                Text(shortDayLabel(day.label))
                    .font(.system(size: 10, weight: isLatest || isSelected ? .semibold : .medium))
                    .foregroundStyle(isSelected || isLatest ? PAXTheme.textPrimary : PAXTheme.textTertiary)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
        }
        .buttonStyle(.plain)
        .accessibilityLabel("\(day.label): \(day.messages) messages")
    }

    private func metricCapsule(title: String, value: String, subtitle: String) -> some View {
        VStack(alignment: .leading, spacing: 3) {
            Text(title)
                .font(.caption2.weight(.medium))
                .foregroundStyle(PAXTheme.textTertiary)
            Text(value)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)
            Text(subtitle)
                .font(.system(size: 9, weight: .medium))
                .foregroundStyle(PAXTheme.textTertiary)
                .lineLimit(1)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(PAXTheme.surface.opacity(colorScheme == .dark ? 0.28 : 0.42))
        )
    }

    @ViewBuilder
    private func selectedDayDetail(_ day: PlatformActivityDay) -> some View {
        HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text(shortDayLabel(day.label))
                    .font(.caption.weight(.semibold))
                Text(L10n.AnalyticsMessages)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            Spacer()
            Text("\(day.messages)")
                .font(.headline.weight(.bold))
            Text("·")
                .foregroundStyle(PAXTheme.textTertiary)
            Text("\(day.sessions)")
                .font(.headline.weight(.semibold))
                .foregroundStyle(PAXTheme.textSecondary)
            Text(L10n.AnalyticsSessions)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textTertiary)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .fill(chartPrimary.opacity(colorScheme == .dark ? 0.12 : 0.08))
        )
    }

    private func premiumGrid(in size: CGSize) -> some View {
        Path { path in
            let rows = 4
            for row in 0...rows {
                let y = size.height * CGFloat(row) / CGFloat(rows)
                path.move(to: CGPoint(x: 0, y: y))
                path.addLine(to: CGPoint(x: size.width, y: y))
            }
        }
        .stroke(PAXTheme.border.opacity(colorScheme == .dark ? 0.12 : 0.16), lineWidth: 0.5)
    }

    private func areaFill(
        values: [CGFloat],
        maxY: CGFloat,
        size: CGSize,
        base: Color,
        peak: Color
    ) -> some View {
        let points = linePoints(values: values, maxY: maxY, size: size)
        return Path { path in
            guard let first = points.first else { return }
            path.move(to: CGPoint(x: first.x, y: size.height))
            path.addLine(to: first)
            for point in points.dropFirst() {
                path.addLine(to: point)
            }
            if let last = points.last {
                path.addLine(to: CGPoint(x: last.x, y: size.height))
            }
            path.closeSubpath()
        }
        .fill(
            LinearGradient(
                colors: [base, peak],
                startPoint: .top,
                endPoint: .bottom
            )
        )
        .opacity(animateChart ? 1 : 0)
        .animation(reduceMotion ? nil : .easeOut(duration: 0.55), value: animateChart)
    }

    private func dayMarkers(maxY: CGFloat, size: CGSize) -> some View {
        ForEach(Array(days.enumerated()), id: \.element.label) { index, day in
            let point = linePoints(values: days.map { CGFloat($0.messages) }, maxY: maxY, size: size)[safe: index]
            if let point {
                Circle()
                    .fill(chartPrimary)
                    .frame(width: selectedDayIndex == index ? 7 : 5, height: selectedDayIndex == index ? 7 : 5)
                    .position(point)
                    .opacity(animateChart ? 1 : 0)
                    .animation(.easeOut(duration: 0.25).delay(Double(index) * 0.04), value: animateChart)
            }
        }
    }

    private func chartLine(
        values: [CGFloat],
        maxY: CGFloat,
        size: CGSize,
        color: Color,
        animate: Bool,
        dashed: Bool = false,
        lineWidth: CGFloat = 2.5
    ) -> some View {
        let points = linePoints(values: values, maxY: maxY, size: size)
        return Path { path in
            guard let first = points.first else { return }
            path.move(to: first)
            for point in points.dropFirst() {
                path.addLine(to: point)
            }
        }
        .trim(from: 0, to: animate ? 1 : 0)
        .stroke(
            color,
            style: StrokeStyle(
                lineWidth: lineWidth,
                lineCap: .round,
                lineJoin: .round,
                dash: dashed ? [5, 4] : []
            )
        )
    }

    private func linePoints(values: [CGFloat], maxY: CGFloat, size: CGSize) -> [CGPoint] {
        guard !values.isEmpty else { return [] }
        let stepX = size.width / CGFloat(max(values.count - 1, 1))
        return values.enumerated().map { index, value in
            let x = CGFloat(index) * stepX
            let normalized = value / maxY
            let y = size.height - (normalized * (size.height - 10)) - 5
            return CGPoint(x: x, y: y)
        }
    }

    private func shortDayLabel(_ raw: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        if let date = formatter.date(from: raw) {
            return MessageTimeFormatter.relativeUpdatedLabel(from: date)
        }
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.count <= 3 ? trimmed : String(trimmed.prefix(3))
    }

    private func startAnimation() {
        guard !reduceMotion else {
            animateChart = true
            return
        }
        withAnimation(.spring(response: 0.78, dampingFraction: 0.86)) {
            animateChart = true
        }
    }
}

private extension Array {
    subscript(safe index: Int) -> Element? {
        indices.contains(index) ? self[index] : nil
    }
}
