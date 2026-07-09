import SwiftUI

struct TeamChatView: View {
    @EnvironmentObject private var auth: AuthStore
    @StateObject private var thread: TeamChatThreadModel

    init(sessionId: String) {
        _thread = StateObject(wrappedValue: TeamChatThreadModel(sessionId: sessionId))
    }

    var body: some View {
        VStack(spacing: 0) {
            ScrollViewReader { proxy in
                ScrollView {
                    LazyVStack(alignment: .leading, spacing: PAXMessageStyle.threadSpacing) {
                        ForEach(Array(thread.messages.enumerated()), id: \.element.id) { index, message in
                            let previous = index > 0 ? thread.messages[index - 1] : nil
                            let next = index + 1 < thread.messages.count ? thread.messages[index + 1] : nil

                            if MessageTimeFormatter.shouldShowDayHeader(current: message, previous: previous),
                               let header = MessageTimeFormatter.dayHeader(from: message.ts) {
                                Text(header)
                                    .font(.caption2.weight(.medium))
                                    .foregroundStyle(PAXTheme.textTertiary)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 8)
                            }

                            MessageBubbleView(
                                message: message,
                                quotedMessage: nil,
                                canReply: false,
                                showTimestamp: MessageTimeFormatter.shouldShowTimestamp(current: message, next: next),
                                onReply: {},
                                onCopy: { UIPasteboard.general.string = message.content },
                                onImageTap: { _ in }
                            )
                            .id(message.id)
                        }
                    }
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                }
                .onChange(of: thread.messages.count) { _ in
                    if let last = thread.messages.last {
                        withAnimation(PAXTheme.quickSpring) {
                            proxy.scrollTo(last.id, anchor: .bottom)
                        }
                    }
                }
            }

            teamComposer
        }
        .background(PAXBackground())
        .navigationTitle(thread.participantName.isEmpty ? L10n.TeamChatTitle : thread.participantName)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { thread.start(auth: auth) }
        .onDisappear { thread.stop() }
    }

    private var canSend: Bool {
        !thread.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && !thread.isSending
    }

    private var teamComposer: some View {
        HStack(spacing: 10) {
            TextField(L10n.TeamChatPlaceholder, text: $thread.draft, axis: .vertical)
                .lineLimit(1...5)
                .padding(.horizontal, 14)
                .padding(.vertical, 10)
                .background(
                    RoundedRectangle(cornerRadius: 22, style: .continuous)
                        .fill(PAXTheme.surfaceElevated)
                )

            Button {
                Task { await thread.send(auth: auth) }
            } label: {
                Image(systemName: "arrow.up.circle.fill")
                    .font(.system(size: 34))
                    .foregroundStyle(canSend ? PAXBrand.accent : PAXTheme.textTertiary)
            }
            .disabled(!canSend)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(PAXTheme.surface.opacity(0.95))
    }
}

struct TeamComposeView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @Environment(\.dismiss) private var dismiss

    @State private var staff: [StaffMember] = []
    @State private var searchText = ""
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var openingUserId: Int?
    @FocusState private var isSearchFocused: Bool

    var onOpenConversation: (String) -> Void

    private var filteredStaff: [StaffMember] {
        let currentId = auth.profile?.userId ?? 0
        var items = staff.filter { $0.userId != currentId && $0.enabled }
        if !searchText.isEmpty {
            let q = searchText.lowercased()
            items = items.filter {
                $0.name.lowercased().contains(q) || $0.email.lowercased().contains(q)
            }
        }
        return items
    }

    var body: some View {
        List {
            Section {
                PAXNativeSearchField(text: $searchText, prompt: L10n.SearchPrompt, isFocused: $isSearchFocused)
                    .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                    .listRowBackground(Color.clear)
            }

            if isLoading {
                Section {
                    HStack {
                        Spacer()
                        ProgressView()
                        Spacer()
                    }
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.footnote)
                        .foregroundStyle(.orange)
                }
            } else if filteredStaff.isEmpty {
                Section {
                    Text(L10n.TeamComposeEmpty)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.TeamComposeSection) {
                    ForEach(filteredStaff) { member in
                        StaffComposeRow(
                            member: member,
                            revealFullEmail: auth.profile?.isSuperAdmin == true,
                            isOpening: openingUserId == member.userId,
                            isDisabled: openingUserId != nil
                        ) {
                            Task { await openChat(with: member) }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.TeamComposeTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await loadStaff() }
    }

    private func loadStaff() async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchStaff()
            staff = response.staff
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func openChat(with member: StaffMember) async {
        openingUserId = member.userId
        defer { openingUserId = nil }
        if let sessionId = await teamCoordinator.openConversation(with: member.userId, auth: auth) {
            dismiss()
            onOpenConversation(sessionId)
        }
    }
}

private struct StaffComposeRow: View {
    let member: StaffMember
    let revealFullEmail: Bool
    let isOpening: Bool
    let isDisabled: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                SessionAvatarView(name: member.name, size: 48, isTeam: true)

                VStack(alignment: .leading, spacing: 3) {
                    Text(member.name)
                        .font(.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.textPrimary)
                    Text(PrivacyMask.email(member.email, revealFull: revealFullEmail))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }

                Spacer()

                if isOpening {
                    ProgressView()
                } else {
                    Image(systemName: "message.fill")
                        .foregroundStyle(PAXBrand.accent)
                }
            }
        }
        .disabled(isDisabled)
    }
}
