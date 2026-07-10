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
                Text("Verwalten Sie Mitarbeiter-Zugang, Profilangaben und Sicherheitseinstellungen.")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Section("Mitarbeiter hinzufügen") {
                HStack {
                    TextField("WordPress E-Mail", text: $addEmail)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.emailAddress)
                    Button("Hinzufügen") {
                        Task { await addStaff() }
                    }
                    .disabled(addEmail.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSaving)
                }
            }

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: "Team wird geladen", rowCount: 4)
                }
            } else if let errorMessage {
                Section {
                    Text(errorMessage).foregroundStyle(PAXTheme.danger)
                }
            } else if staff.isEmpty {
                Section {
                    Text("Noch keine Mitarbeiter konfiguriert.")
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section("Team") {
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
                                    message: "Dieser Mitarbeiter wird aus dem Team entfernt.",
                                    itemTitle: member.name
                                ) {
                                    Task { await removeStaff(member) }
                                }
                            } label: {
                                Label("Entfernen", systemImage: "trash")
                            }
                            .tint(.red)
                        }
                    }
                }
            }
        }
        .navigationTitle("Team")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
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
                    Text(member.enabled ? "Aktiv" : "Inaktiv")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                    Image(systemName: "chevron.right")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.textTertiary)
                }
                Text(PrivacyMask.email(member.email, revealFull: auth.profile?.isSuperAdmin == true))
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text(member.onboardingCompleted ? "Onboarding abgeschlossen" : "Onboarding ausstehend")
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
                    Toggle("Aktiv", isOn: $enabled)
                }
                Section("Profil") {
                    TextField("Anzeigename", text: $displayName)
                    TextField("E-Mail", text: $email)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                    TextField("Avatar URL", text: $avatarURL)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                    TextField("Position", text: $profileTitle)
                    TextField("Telefon", text: $profilePhone)
                    TextField("Notizen", text: $profileNotes, axis: .vertical)
                        .lineLimit(2...5)
                }
                Section("Sicherheit") {
                    SecureField("Neues Passwort (optional)", text: $password)
                    Button("Mitarbeiter sofort abmelden") {
                        PAXDelete.confirm(
                            message: "Der Mitarbeiter wird auf allen Geräten abgemeldet.",
                            itemTitle: member.name,
                            confirmTitle: "Abmelden"
                        ) {
                            onForceLogout()
                        }
                    }
                    .disabled(isForcingLogout)
                }
                Section("Berechtigungen") {
                    PermissionToggle("Chats ansehen", keyPath: \.viewChats, permissions: $permissions)
                    PermissionToggle("Antworten & Chat führen", keyPath: \.replyChats, permissions: $permissions)
                    PermissionToggle("KI-Assistent", keyPath: \.useAI, permissions: $permissions)
                    PermissionToggle("Bilder senden", keyPath: \.sendImages, permissions: $permissions)
                    PermissionToggle("Einstellungen", keyPath: \.manageSettings, permissions: $permissions)
                    PermissionToggle("Bewertungen", keyPath: \.viewRatings, permissions: $permissions)
                    PermissionToggle("Team verwalten", keyPath: \.manageUsers, permissions: $permissions)
                    PermissionToggle("Sicherheit", keyPath: \.accessSecurity, permissions: $permissions)
                    PermissionToggle("Team-Berechtigungen", keyPath: \.manageTeamPermissions, permissions: $permissions)
                    PermissionToggle("Kundenprofile", keyPath: \.manageCustomerProfiles, permissions: $permissions)
                    PermissionToggle("Aufgaben zuweisen", keyPath: \.assignTeamTasks, permissions: $permissions)
                    PermissionToggle("Hub-Profilname ändern", keyPath: \.customizeHubProfile, permissions: $permissions)
                }
            }
            .navigationTitle("Mitarbeiter")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Abbrechen", action: onCancel)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Speichern", action: onSave)
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
