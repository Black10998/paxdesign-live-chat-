import SwiftUI

struct CustomerCybercrimeReportDetailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    let reference: String

    @State private var report: CustomerCybercrimeReport?
    @State private var error: String?
    @State private var isLoading = true
    @State private var draft = ""
    @State private var isSending = false
    @State private var appearedMessageIDs = Set<Int>()

    var body: some View {
        ScrollViewReader { proxy in
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    if isLoading && report == nil {
                        ProgressView()
                            .frame(maxWidth: .infinity)
                            .padding(.top, 40)
                    } else if let error, report == nil {
                        PAXContentUnavailableView(
                            String(localized: "Couldn’t load report"),
                            systemImage: "exclamationmark.triangle",
                            description: Text(error)
                        )
                    } else if let report {
                        statusHeader(report)
                        facts(report)
                        if let attachments = report.attachments, !attachments.isEmpty {
                            attachmentsSection(attachments)
                        }
                        officialThread(report)
                    }
                }
                .padding(.horizontal, PAXSpacing.screenHorizontal)
                .padding(.vertical, 20)
                .padding(.bottom, 24)
            }
            .onChange(of: report?.timeline?.count ?? 0) { _ in
                ChatScrollHelper.schedulePinToBottom(proxy: proxy, animated: true, fallbackId: lastTimelineId)
            }
        }
        .background(PAXTheme.background.ignoresSafeArea())
        .navigationTitle(reference)
        .navigationBarTitleDisplayMode(.inline)
        .safeAreaInset(edge: .bottom) {
            if report?.isOpen == true {
                composer
            }
        }
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Button {
                        Task { await load(force: true) }
                    } label: {
                        PAXLabel(String(localized: "Refresh"), icon: "arrow.clockwise")
                    }
                    Button {
                        navigation.openChatFromCybercrime(reference: reference)
                    } label: {
                        PAXLabel(String(localized: "Ask AI about this report"), icon: "sparkles")
                    }
                } label: {
                    PAXIcon("ellipsis.circle", size: .row, emphasis: .primary)
                }
            }
        }
        .task {
            await load(force: true)
            try? await api.markCybercrimeReportRead(reference)
        }
        .refreshable { await load(force: true) }
    }

    private var lastTimelineId: String {
        if let last = report?.timeline?.last {
            return "t:\(last.id)"
        }
        return ChatScrollHelper.bottomAnchorId
    }

    private func statusHeader(_ report: CustomerCybercrimeReport) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Text(report.displayStatus)
                    .font(PAXTypography.caption.weight(.bold))
                    .foregroundStyle(PAXTheme.onAccent)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(CustomerCybercrimeCatalog.statusTint(report.status), in: Capsule())
                Spacer()
                if report.isOpen {
                    Text(String(localized: "Active"))
                        .font(PAXTypography.caption.weight(.semibold))
                        .foregroundStyle(Color(uiColor: PAXDynamic.income))
                } else {
                    Text(String(localized: "Closed"))
                        .font(PAXTypography.caption.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }
            Text(String(localized: "Reference number"))
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(report.reference_id)
                .font(PAXTypography.titleLarge)
                .foregroundStyle(PAXTheme.textPrimary)
                .textSelection(.enabled)
            Text(String(localized: "Track official status and communication with the PAXDesign team. This record does not include AI assistant chat."))
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
            PAXRevolutSecondaryButton(title: String(localized: "Ask AI about this report")) {
                navigation.openChatFromCybercrime(reference: report.reference_id)
            }
        }
        .padding(18)
        .paxRevolutSurface(cornerRadius: 18, elevation: 1)
    }

    private func facts(_ report: CustomerCybercrimeReport) -> some View {
        VStack(spacing: 0) {
            factRow(String(localized: "Category"), report.displayCategory)
            factRow(String(localized: "Urgency"), CustomerCybercrimeCatalog.urgencyTitle(report.urgency))
            factRow(String(localized: "Submitted"), formattedDate(report.created_at))
            if let platforms = report.platforms, !platforms.isEmpty {
                factRow(String(localized: "Platforms"), platforms)
            }
            if let loss = report.financial_loss, !loss.isEmpty {
                factRow(String(localized: "Financial loss"), "\(loss) \(report.financial_currency ?? "")")
            }
        }
        .paxRevolutSurface(cornerRadius: 16, elevation: 0)
    }

    private func factRow(_ label: String, _ value: String) -> some View {
        HStack(alignment: .top) {
            Text(label)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
            Spacer(minLength: 12)
            Text(value)
                .font(PAXTypography.rowTitle)
                .foregroundStyle(PAXTheme.textPrimary)
                .multilineTextAlignment(.trailing)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
    }

    private func attachmentsSection(_ items: [CustomerCybercrimeAttachment]) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(String(localized: "Attachments").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            ForEach(items) { item in
                HStack {
                    PAXIcon("paperclip", size: .row, emphasis: .secondary)
                    Text(item.name ?? String(localized: "File"))
                        .font(PAXTypography.meta)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Spacer()
                }
                .padding(12)
                .paxRevolutSurface(cornerRadius: 12, elevation: 0)
            }
        }
    }

    private func officialThread(_ report: CustomerCybercrimeReport) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "Official communication").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            if let timeline = report.timeline, !timeline.isEmpty {
                ForEach(timeline) { entry in
                    timelineBubble(entry)
                        .id("t:\(entry.id)")
                }
            } else {
                Text(String(localized: "No official messages yet. The team will reply here after review."))
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Color.clear.frame(height: 8).id(ChatScrollHelper.bottomAnchorId)
            if report.isOpen != true {
                Text(String(localized: "This report is closed. You can view the full history only."))
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
        }
    }

    private func timelineBubble(_ entry: CustomerCybercrimeTimelineEntry) -> some View {
        let outgoing = entry.isCustomer
        return HStack {
            if outgoing { Spacer(minLength: 36) }
            VStack(alignment: outgoing ? .trailing : .leading, spacing: 6) {
                Text(outgoing ? String(localized: "You") : String(localized: "PAXDesign Support"))
                    .font(PAXTypography.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.textTertiary)
                Text(entry.body ?? "")
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .padding(12)
                    .background(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .fill(outgoing ? PAXTheme.accent.opacity(0.14) : PAXTheme.surfaceElevated)
                    )
                if let created = entry.created_at {
                    Text(formattedDate(created))
                        .font(PAXTypography.caption)
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }
            if !outgoing { Spacer(minLength: 36) }
        }
    }

    private var composer: some View {
        HStack(alignment: .bottom, spacing: 8) {
            TextField(String(localized: "Write your message to the team…"), text: $draft, axis: .vertical)
                .lineLimit(1...5)
                .padding(.horizontal, 12)
                .padding(.vertical, 10)
                .background(PAXTheme.surfaceElevated, in: RoundedRectangle(cornerRadius: 18, style: .continuous))
            Button {
                Task { await send() }
            } label: {
                PAXIcon("arrow.up.circle.fill", size: .display, tint: canSend ? PAXTheme.accent : PAXTheme.textTertiary)
                    .frame(width: 44, height: 44)
            }
            .disabled(!canSend)
            .accessibilityLabel(String(localized: "Send"))
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(.ultraThinMaterial)
    }

    private var canSend: Bool {
        !draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !isSending
    }

    private func send() async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        draft = ""
        isSending = true
        defer { isSending = false }
        do {
            let response = try await api.replyToCybercrimeReport(reference, message: text)
            report = response.report ?? report
            MessageSendSound.shared.playIfEnabled()
            PAXHaptics.light()
        } catch {
            draft = text
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func load(force: Bool) async {
        if report == nil || force { isLoading = true }
        do {
            let response = try await api.fetchCybercrimeReport(reference)
            report = response.report
            error = nil
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }

    private func formattedDate(_ raw: String?) -> String {
        guard let raw, !raw.isEmpty else { return "—" }
        let iso = ISO8601DateFormatter()
        iso.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        let iso2 = ISO8601DateFormatter()
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        if let date = iso.date(from: raw) ?? iso2.date(from: raw) {
            return formatter.string(from: date)
        }
        return raw.replacingOccurrences(of: "T", with: " ")
    }
}
