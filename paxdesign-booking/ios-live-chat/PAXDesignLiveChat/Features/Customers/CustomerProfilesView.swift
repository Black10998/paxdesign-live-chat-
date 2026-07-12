import SwiftUI

struct CustomerProfilesView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var profiles: [CustomerProfileRecord] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var searchText = ""
    @State private var draft: CustomerProfileDraft?

    private var filteredProfiles: [CustomerProfileRecord] {
        let query = searchText.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard !query.isEmpty else { return profiles }
        return profiles.filter { profile in
            profile.displayName.lowercased().contains(query)
                || profile.sessionId.lowercased().contains(query)
                || profile.email.lowercased().contains(query)
        }
    }

    var body: some View {
        List {
            Section {
                Text(L10n.CustomerManagementHint)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.CustomerLoading, rowCount: 4)
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage)
                        .foregroundStyle(PAXTheme.danger)
                }
            } else if filteredProfiles.isEmpty {
                Section {
                    Text(L10n.CustomerNoneFound)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.CustomerSection) {
                    ForEach(filteredProfiles) { profile in
                        Button {
                            draft = CustomerProfileDraft(record: profile)
                        } label: {
                            row(for: profile)
                        }
                        .buttonStyle(.plain)
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .searchable(text: $searchText, prompt: L10n.CustomerSearchPrompt)
        .navigationTitle(L10n.CustomerTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .paxPremiumRefreshable(status: L10n.CustomerLoading, rowCount: 4) { await load() }
        .sheet(item: $draft) { item in
            NavigationStack {
                CustomerProfileEditSheet(
                    draft: item,
                    onSave: { updated in
                        Task { await save(updated) }
                    },
                    onCancel: { draft = nil }
                )
            }
            .presentationDetents([.large])
        }
    }

    private func row(for profile: CustomerProfileRecord) -> some View {
        HStack(spacing: 12) {
            avatar(urlString: profile.avatarUrl)
            VStack(alignment: .leading, spacing: 3) {
                Text(profile.displayName.isEmpty ? L10n.CustomerDefaultName : profile.displayName)
                    .font(.body.weight(.semibold))
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(profile.sessionId)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                if !profile.email.isEmpty {
                    Text(profile.email)
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textTertiary)
                }
            }
            Spacer()
            PAXIcon( "chevron.right")
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
        }
        .padding(.vertical, 4)
    }

    private func avatar(urlString: String) -> some View {
        Group {
            if let url = URL(string: urlString), !urlString.isEmpty {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        Circle().fill(PAXTheme.accentSoft)
                    }
                }
            } else {
                Circle().fill(PAXTheme.accentSoft)
            }
        }
        .frame(width: 38, height: 38)
        .clipShape(Circle())
    }

    private func load() async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            profiles = try await api.fetchPlatformCustomerProfiles()
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func save(_ updated: CustomerProfileDraft) async {
        guard let api = auth.api else { return }
        let payload: [String: Any] = [
            "session_id": updated.sessionId,
            "display_name": updated.displayName,
            "avatar_url": updated.avatarUrl,
            "email": updated.email,
            "phone": updated.phone,
            "company": updated.company,
            "notes": updated.notes,
            "visible_details": [
                "show_email": updated.showEmail,
                "show_phone": updated.showPhone,
                "show_company": updated.showCompany,
                "show_notes": updated.showNotes,
            ],
        ]
        do {
            let saved = try await api.savePlatformCustomerProfile(payload)
            if let index = profiles.firstIndex(where: { $0.sessionId == saved.sessionId }) {
                profiles[index] = saved
            } else {
                profiles.insert(saved, at: 0)
            }
            draft = nil
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}

private struct CustomerProfileDraft: Identifiable {
    var id: String { sessionId }
    let sessionId: String
    var displayName: String
    var avatarUrl: String
    var email: String
    var phone: String
    var company: String
    var notes: String
    var showEmail: Bool
    var showPhone: Bool
    var showCompany: Bool
    var showNotes: Bool

    init(record: CustomerProfileRecord) {
        sessionId = record.sessionId
        displayName = record.displayName
        avatarUrl = record.avatarUrl
        email = record.email
        phone = record.phone
        company = record.company
        notes = record.notes
        showEmail = record.visibleDetails.showEmail
        showPhone = record.visibleDetails.showPhone
        showCompany = record.visibleDetails.showCompany
        showNotes = record.visibleDetails.showNotes
    }
}

private struct CustomerProfileEditSheet: View {
    @State var draft: CustomerProfileDraft
    let onSave: (CustomerProfileDraft) -> Void
    let onCancel: () -> Void

    var body: some View {
        Form {
            Section(L10n.CustomerEditSectionSession) {
                LabeledContent(L10n.CustomerEditFieldSessionId, value: draft.sessionId)
                TextField(L10n.CommonFieldDisplayName, text: $draft.displayName)
                TextField(L10n.StaffFieldAvatarUrl, text: $draft.avatarUrl)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
            }

            Section(L10n.CustomerEditSectionContact) {
                TextField(L10n.CommonFieldEmail, text: $draft.email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                TextField(L10n.CommonFieldPhone, text: $draft.phone)
                    .keyboardType(.phonePad)
                TextField(L10n.CustomerEditFieldCompany, text: $draft.company)
                TextField(L10n.CommonFieldNotes, text: $draft.notes, axis: .vertical)
                    .lineLimit(2...5)
            }

            Section(L10n.CustomerEditSectionVisible) {
                Toggle(L10n.CustomerEditShowEmail, isOn: $draft.showEmail)
                Toggle(L10n.CustomerEditShowPhone, isOn: $draft.showPhone)
                Toggle(L10n.CustomerEditShowCompany, isOn: $draft.showCompany)
                Toggle(L10n.CustomerEditShowNotes, isOn: $draft.showNotes)
            }
        }
        .navigationTitle(L10n.CustomerEditTitle)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button(L10n.CommonCancel, action: onCancel)
            }
            ToolbarItem(placement: .confirmationAction) {
                Button(L10n.CommonSave) { onSave(draft) }
            }
        }
    }
}
