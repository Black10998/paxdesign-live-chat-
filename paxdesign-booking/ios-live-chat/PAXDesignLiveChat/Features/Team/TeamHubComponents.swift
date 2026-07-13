import SwiftUI

// MARK: - Monochrome Team Hub (Apple-style glass, vector icons only)

struct TeamHubProfileCard: View {
    let displayName: String
    let roleLabel: String
    let email: String

    var body: some View {
        HStack(spacing: 16) {
            ProfileAvatarView(size: 58)
            VStack(alignment: .leading, spacing: 5) {
                Text(displayName)
                    .font(.title3.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(2)
                Text(roleLabel)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                if !email.isEmpty {
                    Text(email)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textTertiary)
                        .lineLimit(1)
                }
            }
            Spacer(minLength: 0)
        }
        .padding(18)
        .paxPremiumGlass(tier: .premium, cornerRadius: 20)
    }
}

struct TeamHubMetricTile: View {
    let icon: String
    let value: String
    let label: String

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            PAXIcon(icon, size: .row, emphasis: .secondary)
            Text(value)
                .font(.system(size: 28, weight: .semibold, design: .rounded))
                .foregroundStyle(PAXTheme.textPrimary)
                .contentTransition(.numericText())
                .monospacedDigit()
            Text(label)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
                .lineLimit(2)
                .fixedSize(horizontal: false, vertical: true)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(14)
        .paxPremiumGlass(tier: .standard, cornerRadius: 16)
    }
}

struct TeamHubActionRow: View {
    let icon: String
    let title: String
    let subtitle: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                PAXIcon(icon, size: .hero, emphasis: .primary)
                    .frame(width: 40, height: 40)
                    .background(
                        Circle()
                            .fill(Color.primary.opacity(0.05))
                    )
                VStack(alignment: .leading, spacing: 3) {
                    Text(title)
                        .font(.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(subtitle)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .multilineTextAlignment(.leading)
                }
                Spacer(minLength: 0)
                PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .paxPremiumGlass(tier: .standard, cornerRadius: 16)
        }
        .buttonStyle(.plain)
    }
}

struct TeamHubMemberRow: View {
    let member: StaffMember
    let isOpening: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                ZStack(alignment: .bottomTrailing) {
                    StaffAvatarView(name: member.displayName, avatarUrl: member.avatarUrl, size: 46)
                    TeamPresenceGlyph(status: member.presenceStatus)
                        .offset(x: 2, y: 2)
                }
                VStack(alignment: .leading, spacing: 4) {
                    Text(member.displayName)
                        .font(.body.weight(.medium))
                        .foregroundStyle(PAXTheme.textPrimary)
                        .lineLimit(1)
                    Text(member.publicDisplaySubtitle)
                        .font(.caption.weight(.medium))
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer(minLength: 0)
                VStack(alignment: .trailing, spacing: 4) {
                    Text(member.presenceLabel)
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textTertiary)
                    if isOpening {
                        ProgressView()
                            .scaleEffect(0.85)
                    } else {
                        PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        }
        .buttonStyle(.plain)
        .disabled(isOpening)
    }
}

struct TeamHubAlertRow: View {
    let icon: String
    let title: String
    let subtitle: String
    let timeLabel: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(alignment: .top, spacing: 12) {
                PAXIcon(icon, size: .card, emphasis: .secondary)
                    .frame(width: 32, height: 32)
                    .background(
                        Circle()
                            .stroke(PAXTheme.border.opacity(0.4), lineWidth: 0.5)
                    )
                VStack(alignment: .leading, spacing: 4) {
                    Text(title)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(subtitle)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .lineLimit(2)
                }
                Spacer(minLength: 0)
                Text(timeLabel)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .paxPremiumGlass(tier: .subtle, cornerRadius: 14)
        }
        .buttonStyle(.plain)
    }
}

struct TeamBroadcastSheet: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @Environment(\.dismiss) private var dismiss

    @State private var message = ""
    @State private var isSending = false
    @State private var progress = ""
    @State private var error: String?

    let recipients: [StaffMember]
    var onOpenSession: (String) -> Void = { _ in }

    var body: some View {
        Form {
            Section {
                Text(L10n.TeamBroadcastHint)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Section(L10n.TeamBroadcastMessage) {
                TextField(L10n.TeamChatPlaceholder, text: $message, axis: .vertical)
                    .lineLimit(3...8)
            }
            Section {
                Text(String(format: L10n.TeamBroadcastRecipientCount, recipients.count))
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            if !progress.isEmpty {
                Section {
                    Text(progress)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
        .navigationTitle(L10n.TeamMessageTeam)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button(L10n.CommonCancel) { dismiss() }
            }
            ToolbarItem(placement: .confirmationAction) {
                Button(L10n.CommonSend) {
                    Task { await sendBroadcast() }
                }
                .disabled(isSending || message.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
        }
        .alert(L10n.TeamSendErrorTitle, isPresented: Binding(
            get: { error != nil },
            set: { if !$0 { error = nil } }
        )) {
            Button(L10n.CommonOK, role: .cancel) { error = nil }
        } message: {
            Text(error ?? "")
        }
        .disabled(isSending)
    }

    private func sendBroadcast() async {
        let text = message.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        isSending = true
        defer { isSending = false }

        var sent = 0
        for (index, member) in recipients.enumerated() {
            progress = String(format: L10n.TeamBroadcastProgress, index + 1, recipients.count)
            guard let sessionId = await teamCoordinator.openConversation(with: member.userId, auth: auth) else {
                continue
            }
            if let api = auth.api {
                _ = try? await api.sendTeamMessage(sessionId, content: text)
            }
            sent += 1
        }
        progress = String(format: L10n.TeamBroadcastDone, sent)
        PAXHaptics.success()
        try? await Task.sleep(nanoseconds: 600_000_000)
        dismiss()
    }
}
