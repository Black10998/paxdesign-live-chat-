import SwiftUI

struct StaffManagementView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var staff: [StaffMember] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var addEmail = ""
    @State private var isSaving = false
    @State private var editingMember: StaffMember?
    @State private var editEnabled = true
    @State private var editPermissions = AdminPermissions()
    @State private var editDisplayName = ""
    @State private var editEmail = ""
    @State private var editAvatarURL = ""
    @State private var editProfileTitle = ""
    @State private var editProfilePhone = ""
    @State private var editProfileNotes = ""
    @State private var editPassword = ""
    @State private var isForcingLogout = false

    var body: some View {
        List {
            Section {
                Text(L10n.StaffManagementHint)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Section(L10n.AdminSectionAddMember) {
                HStack {
                    TextField(L10n.StaffWordpressEmail, text: $addEmail)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.emailAddress)
                    Button(L10n.CommonAdd) {
                        Task { await addStaff() }
                    }
                    .disabled(addEmail.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSaving)
                }
            }

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.LoadingTeam, rowCount: 4)
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage).foregroundStyle(PAXTheme.danger)
                }
            } else if staff.isEmpty {
                Section {
                    Text(L10n.StaffNoneConfigured)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.AccountTeam) {
                    ForEach(staff) { member in
                        Button {
                            editingMember = member
                            editEnabled = member.enabled
                            editPermissions = member.permissions
                            editDisplayName = member.name
                            editEmail = member.email
                            editAvatarURL = member.avatarUrl ?? ""
                            editProfileTitle = member.profileTitle ?? ""
                            editProfilePhone = member.profilePhone ?? ""
                            editProfileNotes = member.profileNotes ?? ""
                            editPassword = ""
                        } label: {
                            staffRow(member)
                        }
                        .buttonStyle(.plain)
                        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                            Button {
                                PAXDelete.confirm(
                                    message: L10n.StaffRemoveConfirmMessage,
                                    itemTitle: member.name
                                ) {
                                    Task { await removeStaff(member) }
                                }
                            } label: {
                                Label(L10n.CommonRemove, systemImage: "trash")
                            }
                            .tint(.red)
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.AccountTeam)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .paxPremiumRefreshable(status: L10n.LoadingTeam, rowCount: 4) { await load() }
        .sheet(item: $editingMember) { member in
            StaffEditSheet(
                member: member,
                enabled: $editEnabled,
                permissions: $editPermissions,
                displayName: $editDisplayName,
                email: $editEmail,
                avatarURL: $editAvatarURL,
                profileTitle: $editProfileTitle,
                profilePhone: $editProfilePhone,
                profileNotes: $editProfileNotes,
                password: $editPassword,
                isSaving: isSaving,
                isForcingLogout: isForcingLogout,
                onSave: { Task { await saveMember(member) } },
                onForceLogout: { Task { await forceLogout(member) } },
                onCancel: { editingMember = nil }
            )
        }
    }

    private func staffRow(_ member: StaffMember) -> some View {
        HStack(spacing: 10) {
            Group {
                if let avatarURL = member.avatarUrl, let url = URL(string: avatarURL), !avatarURL.isEmpty {
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
            .frame(width: 34, height: 34)
            .clipShape(Circle())

            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(member.name)
                        .font(.headline)
                        .foregroundStyle(PAXTheme.textPrimary)
                    Spacer()
                    Text(member.enabled ? L10n.CommonActive : L10n.CommonInactive)
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                    Image(systemName: "chevron.right")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                }
                Text(member.publicDisplaySubtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text(member.onboardingCompleted ? L10n.StaffOnboardingComplete : L10n.StaffOnboardingPending)
                    .font(.caption2)
                    .foregroundStyle(member.onboardingCompleted ? PAXTheme.textTertiary : PAXTheme.danger)
            }
        }
        .padding(.vertical, 4)
    }

    private func load() async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchStaff()
            staff = response.staff
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func addStaff() async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            try await api.saveStaff(
                email: addEmail,
                enabled: true,
                permissions: AdminPermissions(
                    viewChats: true,
                    replyChats: true,
                    useAI: true,
                    sendImages: true,
                    manageSettings: false,
                    viewRatings: false,
                    manageUsers: false,
                    accessSecurity: false,
                    manageTeamPermissions: false,
                    manageCustomerProfiles: false,
                    assignTeamTasks: false,
                    customizeHubProfile: false
                )
            )
            addEmail = ""
            await load()
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func saveMember(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            try await api.saveStaff(
                userId: member.userId,
                email: editEmail,
                enabled: editEnabled,
                permissions: editPermissions,
                displayName: editDisplayName,
                avatarURL: editAvatarURL,
                profileTitle: editProfileTitle,
                profilePhone: editProfilePhone,
                profileNotes: editProfileNotes,
                password: editPassword.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? nil : editPassword
            )
            editingMember = nil
            await load()
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func forceLogout(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        isForcingLogout = true
        defer { isForcingLogout = false }
        do {
            try await api.forceLogoutStaff(userId: member.userId)
            await load()
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func removeStaff(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        do {
            try await api.removeStaff(userId: member.userId)
            await load()
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

private struct StaffEditSheet: View {
    let member: StaffMember
    @Binding var enabled: Bool
    @Binding var permissions: AdminPermissions
    @Binding var displayName: String
    @Binding var email: String
    @Binding var avatarURL: String
    @Binding var profileTitle: String
    @Binding var profilePhone: String
    @Binding var profileNotes: String
    @Binding var password: String
    let isSaving: Bool
    let isForcingLogout: Bool
    let onSave: () -> Void
    let onForceLogout: () -> Void
    let onCancel: () -> Void

    var body: some View {
        NavigationStack {
            List {
                Section(member.name) {
                    Toggle(L10n.CommonActive, isOn: $enabled)
                }
                Section(L10n.SettingsProfile) {
                    TextField(L10n.CommonFieldDisplayName, text: $displayName)
                    TextField(L10n.CommonFieldEmail, text: $email)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                    TextField(L10n.StaffFieldAvatarUrl, text: $avatarURL)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                    TextField(L10n.StaffFieldPosition, text: $profileTitle)
                    TextField(L10n.CommonFieldPhone, text: $profilePhone)
                    TextField(L10n.CommonFieldNotes, text: $profileNotes, axis: .vertical)
                        .lineLimit(2...5)
                }
                Section(L10n.SettingsSecurity) {
                    SecureField(L10n.StaffNewPasswordPlaceholder, text: $password)
                    Button(L10n.StaffForceLogout) {
                        PAXDelete.confirm(
                            message: L10n.StaffForceLogoutMessage,
                            itemTitle: member.name,
                            confirmTitle: L10n.StaffForceLogoutConfirm
                        ) {
                            onForceLogout()
                        }
                    }
                    .disabled(isForcingLogout)
                }
                Section(L10n.AdminSectionPermissions) {
                    PermissionToggle(L10n.PermissionViewChats, keyPath: \.viewChats, permissions: $permissions)
                    PermissionToggle(L10n.PermissionReplyChats, keyPath: \.replyChats, permissions: $permissions)
                    PermissionToggle(L10n.PermissionAIAssistant, keyPath: \.useAI, permissions: $permissions)
                    PermissionToggle(L10n.PermissionSendImages, keyPath: \.sendImages, permissions: $permissions)
                    PermissionToggle(L10n.PermissionSettings, keyPath: \.manageSettings, permissions: $permissions)
                    PermissionToggle(L10n.PermissionRatings, keyPath: \.viewRatings, permissions: $permissions)
                    PermissionToggle(L10n.PermissionManageTeam, keyPath: \.manageUsers, permissions: $permissions)
                    PermissionToggle(L10n.PermissionSecurity, keyPath: \.accessSecurity, permissions: $permissions)
                    PermissionToggle(L10n.PermissionTeamPermissions, keyPath: \.manageTeamPermissions, permissions: $permissions)
                    PermissionToggle(L10n.PermissionCustomerProfiles, keyPath: \.manageCustomerProfiles, permissions: $permissions)
                    PermissionToggle(L10n.PermissionAssignTasks, keyPath: \.assignTeamTasks, permissions: $permissions)
                    PermissionToggle(L10n.PermissionHubProfile, keyPath: \.customizeHubProfile, permissions: $permissions)
                }
            }
            .navigationTitle(L10n.StaffTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonCancel, action: onCancel)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(L10n.CommonSave, action: onSave)
                        .disabled(isSaving)
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
}

private struct PermissionToggle: View {
    let title: String
    let keyPath: WritableKeyPath<AdminPermissions, Bool>
    @Binding var permissions: AdminPermissions

    init(_ title: String, keyPath: WritableKeyPath<AdminPermissions, Bool>, permissions: Binding<AdminPermissions>) {
        self.title = title
        self.keyPath = keyPath
        self._permissions = permissions
    }

    var body: some View {
        Toggle(title, isOn: Binding(
            get: { permissions[keyPath: keyPath] },
            set: { newValue in
                var copy = permissions
                copy[keyPath: keyPath] = newValue
                permissions = copy
            }
        ))
    }
}

extension StaffMember: Hashable {
    static func == (lhs: StaffMember, rhs: StaffMember) -> Bool { lhs.userId == rhs.userId }
    func hash(into hasher: inout Hasher) { hasher.combine(userId) }
}
