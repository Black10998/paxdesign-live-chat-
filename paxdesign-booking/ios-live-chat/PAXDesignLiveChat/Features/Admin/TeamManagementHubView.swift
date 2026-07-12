import SwiftUI

struct TeamManagementHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator

    @State private var overview: TeamManagementOverview?
    @State private var members: [StaffMember] = []
    @State private var pendingRequests: [LiveSession] = []
    @State private var policy: TeamContactPolicy?
    @State private var requireAdminApproval = true
    @State private var requireManagerApproval = false
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var addEmail = ""
    @State private var isSaving = false
    @State private var editingMember: StaffMember?
    @State private var editEnabled = true
    @State private var editRole: TeamRoleKey = .teamMember
    @State private var editPermissions = AdminPermissions()

    private var isExecutiveDirector: Bool { auth.profile?.isSuperAdmin == true }

    private var executiveDirectorName: String {
        members.first(where: \.isExecutive)?.name ?? L10n.AdminOverviewEd
    }

    var body: some View {
        Group {
            if isExecutiveDirector {
                managementContent
            } else {
                accessDenied
            }
        }
        .navigationTitle(L10n.AdminTeamManagement)
        .navigationBarTitleDisplayMode(.large)
        .task { await reload() }
        .paxPremiumRefreshable(status: L10n.LoadingTeamManagement, rowCount: 5) {
            await reload()
        }
        .sheet(item: $editingMember) { member in
            memberEditSheet(member)
        }
    }

    private var accessDenied: some View {
        VStack(spacing: 16) {
            PAXIcon("lock.shield", size: .hero, emphasis: .secondary)
            Text(L10n.AdminAccessDeniedTitle)
                .font(.title3.weight(.semibold))
            Text(L10n.AdminAccessDeniedSubtitle)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding()
        .paxScreenBackground()
    }

    private var managementContent: some View {
        List {
            heroSection
            overviewSection
            pendingSection
            hierarchySection
            membersSection
            addMemberSection
            policySection
            messagingSection
            if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.danger)
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
    }

    private var heroSection: some View {
        Section {
            PlatformHeroHeader(
                title: L10n.AdminTeamManagement,
                subtitle: L10n.AdminTeamManagementSubtitle,
                systemImage: "person.3.sequence.fill",
                tint: PAXTheme.accent
            )
            .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
        }
    }

    @ViewBuilder
    private var overviewSection: some View {
        if let overview {
            Section(L10n.AdminSectionOverview) {
                LabeledContent(L10n.AdminOverviewEd, value: executiveDirectorName)
                LabeledContent(L10n.AdminOverviewMembers, value: "\(overview.totalMembers)")
                LabeledContent(L10n.AdminOverviewActive, value: "\(overview.enabledMembers)")
                LabeledContent(L10n.AdminOverviewPending, value: "\(overview.pendingRequestCount)")
            }
        } else if isLoading {
            Section {
                PAXScreenLoadingStack(status: L10n.LoadingOverview, rowCount: 2)
            }
        }
    }

    @ViewBuilder
    private var pendingSection: some View {
        if !pendingRequests.isEmpty {
            Section(L10n.TeamPendingRequests) {
                ForEach(pendingRequests) { session in
                    VStack(alignment: .leading, spacing: 10) {
                        HStack {
                            SessionAvatarView(name: session.displayName, size: 40, isTeam: true)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(session.displayName)
                                    .font(.body.weight(.semibold))
                                Text(session.otherRoleLabel.isEmpty ? session.requestStatusLabel : session.otherRoleLabel)
                                    .font(.caption)
                                    .foregroundStyle(PAXTheme.accent)
                            }
                            Spacer()
                        }
                        if !session.lastPreview.isEmpty {
                            Text(session.lastPreview)
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textSecondary)
                                .lineLimit(2)
                        }
                        HStack(spacing: 12) {
                            Button(L10n.TeamActionDecline) {
                                Task { await respond(session, accept: false) }
                            }
                            .buttonStyle(.bordered)
                            .tint(PAXTheme.danger)
                            Button(L10n.TeamActionApprove) {
                                Task { await respond(session, accept: true) }
                            }
                            .buttonStyle(.borderedProminent)
                            .tint(PAXBrand.accent)
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
        }
    }

    @ViewBuilder
    private var hierarchySection: some View {
        if let overview, !overview.hierarchy.isEmpty {
            Section(L10n.AdminSectionHierarchy) {
                ForEach(overview.hierarchy) { level in
                    if !level.members.isEmpty {
                        DisclosureGroup {
                            ForEach(level.members) { member in
                                HStack {
                                    Text(member.name)
                                        .font(.subheadline.weight(.semibold))
                                    Spacer()
                                    Text(member.enabled ? L10n.CommonActive : L10n.CommonInactive)
                                        .font(.caption2.weight(.semibold))
                                        .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                                }
                            }
                        } label: {
                            HStack {
                                Text(level.roleLabel)
                                    .font(.subheadline.weight(.semibold))
                                Spacer()
                                Text("\(level.members.count)")
                                    .font(.caption.weight(.bold))
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                        }
                    }
                }
            }
        }
    }

    @ViewBuilder
    private var membersSection: some View {
        if isLoading && members.isEmpty {
            Section {
                PAXScreenLoadingStack(status: L10n.LoadingTeam, rowCount: 4)
            }
        } else {
            Section(L10n.AdminSectionTeamRoster) {
                ForEach(members.filter { !$0.isExecutive }) { member in
                    Button {
                        editingMember = member
                        editEnabled = member.enabled
                        editRole = TeamRoleKey(rawValue: member.teamRole ?? "") ?? .teamMember
                        editPermissions = member.permissions
                    } label: {
                        HStack(spacing: 12) {
                            SessionAvatarView(name: member.name, size: 40, isTeam: true)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(member.name)
                                    .font(.body.weight(.semibold))
                                    .foregroundStyle(PAXTheme.textPrimary)
                                Text(member.publicDisplaySubtitle)
                                    .font(.caption.weight(.semibold))
                                    .foregroundStyle(PAXTheme.accent)
                            }
                            Spacer()
                            Text(member.enabled ? L10n.CommonActive : L10n.CommonInactive)
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                            PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                        }
                        .padding(.vertical, 2)
                    }
                    .buttonStyle(.plain)
                    .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                        if !member.isProtected {
                            Button(role: .destructive) {
                                PAXDelete.confirm(
                                    title: L10n.CommonRemove,
                                    message: L10n.AdminConfirmRemoveMember,
                                    itemTitle: member.name,
                                    confirmTitle: L10n.CommonRemove
                                ) {
                                    Task { await removeMember(member) }
                                }
                            } label: {
                                Label { Text(L10n.CommonRemove) } icon: { PAXIcon("trash") }
                            }
                        }
                    }
                }
            }
        }
    }

    private var addMemberSection: some View {
        Section(L10n.AdminSectionAddMember) {
            HStack {
                TextField(L10n.AdminFieldWordpressEmail, text: $addEmail)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                Button(L10n.CommonAdd) {
                    Task { await addMember() }
                }
                .disabled(addEmail.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSaving)
            }
            Text(L10n.AdminAddMemberHint)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private var policySection: some View {
        Section(L10n.AdminSectionContactPolicy) {
            Toggle(L10n.AdminPolicyRequireAdminApproval, isOn: $requireAdminApproval)
                .onChange(of: requireAdminApproval) { _ in
                    Task { await savePolicy() }
                }
            Toggle(L10n.AdminPolicyRequireManagerApproval, isOn: $requireManagerApproval)
                .onChange(of: requireManagerApproval) { _ in
                    Task { await savePolicy() }
                }
            LabeledContent(L10n.AdminPolicyEdMessaging) {
                Text(L10n.AdminPolicyRequestRequired)
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(PAXTheme.accent)
            }
            Text(L10n.AdminPolicyEdHint)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private var messagingSection: some View {
        Section(L10n.AdminSectionConversations) {
            NavigationLink {
                TeamMessagesHubView()
            } label: {
                Label { Text(L10n.AdminTeamMessagesInbox) } icon: { PAXIcon("bubble.left.and.bubble.right") }
            }
        }
    }

    private func memberEditSheet(_ member: StaffMember) -> some View {
        NavigationStack {
            List {
                Section(member.name) {
                    Toggle(L10n.CommonActive, isOn: $editEnabled)
                    Picker(L10n.AdminMemberRole, selection: $editRole) {
                        ForEach(TeamRoleKey.assignable) { role in
                            Text(role.label).tag(role)
                        }
                    }
                }
                Section(L10n.AdminSectionPermissions) {
                    PermissionToggle(L10n.PermissionViewChats, keyPath: \.viewChats, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionReplyChats, keyPath: \.replyChats, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionAIAssistant, keyPath: \.useAI, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionSendImages, keyPath: \.sendImages, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionSettings, keyPath: \.manageSettings, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionRatings, keyPath: \.viewRatings, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionManageTeam, keyPath: \.manageUsers, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionSecurity, keyPath: \.accessSecurity, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionTeamPermissions, keyPath: \.manageTeamPermissions, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionCustomerProfiles, keyPath: \.manageCustomerProfiles, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionAssignTasks, keyPath: \.assignTeamTasks, permissions: $editPermissions)
                    PermissionToggle(L10n.PermissionHubProfile, keyPath: \.customizeHubProfile, permissions: $editPermissions)
                }
            }
            .navigationTitle(L10n.AdminEditMemberTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonCancel) { editingMember = nil }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(L10n.CommonSave) {
                        Task { await saveMember(member) }
                    }
                    .disabled(isSaving)
                }
            }
        }
        .presentationDetents([.medium, .large])
    }

    private func reload() async {
        guard isExecutiveDirector, let api = auth.api else {
            isLoading = false
            return
        }
        isLoading = true
        defer { isLoading = false }
        do {
            async let overviewTask = api.fetchTeamManagementOverview()
            async let membersTask = api.fetchTeamManagementMembers()
            async let pendingTask = api.fetchTeamManagementPendingRequests()
            overview = try await overviewTask
            members = try await membersTask.members
            pendingRequests = try await pendingTask.sessions
            if let loadedPolicy = overview?.policy {
                policy = loadedPolicy
                requireAdminApproval = loadedPolicy.requireAdminApproval
                requireManagerApproval = loadedPolicy.requireManagerApproval
            }
            errorMessage = nil
            await teamCoordinator.refreshPendingRequests(auth: auth)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func addMember() async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            _ = try await api.addTeamManagementMember(
                email: addEmail.trimmingCharacters(in: .whitespacesAndNewlines),
                teamRole: TeamRoleKey.teamMember.rawValue
            )
            addEmail = ""
            await reload()
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
            _ = try await api.updateTeamManagementMember(
                userId: member.userId,
                teamRole: editRole.rawValue,
                enabled: editEnabled,
                permissions: editPermissions
            )
            editingMember = nil
            await reload()
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func removeMember(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        do {
            try await api.removeTeamManagementMember(userId: member.userId)
            await reload()
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func savePolicy() async {
        guard let api = auth.api else { return }
        do {
            let response = try await api.saveTeamManagementPolicy(
                requireAdminApproval: requireAdminApproval,
                requireManagerApproval: requireManagerApproval
            )
            policy = response.policy
            PAXHaptics.light()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func respond(_ session: LiveSession, accept: Bool) async {
        _ = await teamCoordinator.respondToRequest(sessionId: session.sessionId, accept: accept, auth: auth)
        await reload()
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
