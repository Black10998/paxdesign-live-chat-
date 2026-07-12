import SwiftUI

struct TasksModuleView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var store = TaskStore.shared
    @ObservedObject private var moduleSettings = PlatformModuleSettingsStore.shared
    @State private var showAdd = false
    @State private var filter: TaskFilter = .open
    @State private var teamMembers: [TeamMemberRecord] = []
    @State private var isInitialLoading = true

    private enum TaskFilter: String, CaseIterable, Identifiable {
        case open, completed, all
        var id: String { rawValue }
        var title: String {
            switch self {
            case .open: return L10n.FilterActive
            case .completed: return L10n.FilterClosed
            case .all: return L10n.FilterAll
            }
        }
    }

    private var filteredTasks: [PAXTaskItem] {
        let base: [PAXTaskItem]
        switch filter {
        case .open:
            base = store.tasks.filter { !$0.isCompleted }
        case .completed:
            base = store.tasks.filter(\.isCompleted)
        case .all:
            base = moduleSettings.tasksShowCompleted ? store.tasks : store.tasks.filter { !$0.isCompleted }
        }

        if moduleSettings.tasksSortByDueDate {
            return base.sorted { lhs, rhs in
                switch (lhs.dueDate, rhs.dueDate) {
                case let (l?, r?): return l < r
                case (.some, .none): return true
                case (.none, .some): return false
                case (.none, .none): return lhs.createdAt > rhs.createdAt
                }
            }
        }
        return base
    }

    var body: some View {
        List {
            if isInitialLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.ModuleTasks, rowCount: 4)
                }
            } else {
            Section {
                Picker(L10n.FilterAll, selection: $filter) {
                    ForEach(TaskFilter.allCases) { item in
                        Text(item.title).tag(item)
                    }
                }
                .pickerStyle(.segmented)
                .listRowBackground(Color.clear)
            }

            if filteredTasks.isEmpty {
                Section {
                    Text(L10n.TasksEmpty)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.ModuleTasks) {
                    ForEach(filteredTasks) { task in
                        taskRow(task)
                    }
                }
            }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleTasks)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleTasksSettingsView() } label: {
                    PAXIcon( "slider.horizontal.3")
                }
            }
            if auth.canAssignTeamTasks {
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showAdd = true } label: {
                        PAXIcon( "plus")
                    }
                }
            }
        }
        .sheet(isPresented: $showAdd) {
            NavigationStack {
                AddTaskSheet(
                    members: auth.canAssignTeamTasks ? teamMembers : [],
                    canAssign: auth.canAssignTeamTasks,
                    onSave: { title, notes, dueDate, priority, assignedUserId in
                        Task {
                            await store.add(
                                title: title,
                                notes: notes,
                                dueDate: dueDate,
                                priority: priority,
                                assignedUserId: auth.canAssignTeamTasks ? assignedUserId : 0,
                                auth: auth
                            )
                            showAdd = false
                            PAXHaptics.success()
                        }
                    },
                    onCancel: { showAdd = false }
                )
            }
            .presentationDetents([.medium, .large])
        }
        .task {
            await loadTeamMembers()
            await PlatformSyncService.shared.sync(auth: auth)
            withAnimation(.easeOut(duration: 0.25)) {
                isInitialLoading = false
            }
        }
        .paxPremiumRefreshable(status: L10n.ModuleTasks, rowCount: 4) {
            await PlatformSyncService.shared.sync(auth: auth)
            await loadTeamMembers()
        }
    }

    private func taskRow(_ task: PAXTaskItem) -> some View {
        HStack(spacing: 12) {
            Button {
                Task {
                    await store.toggleComplete(task, auth: auth)
                    PAXHaptics.light()
                }
            } label: {
                PAXIcon( task.isCompleted ? "checkmark.circle.fill" : "circle")
                    .font(.title3)
                    .foregroundStyle(task.isCompleted ? PAXTheme.success : PAXTheme.textTertiary)
            }
            .buttonStyle(.plain)

            VStack(alignment: .leading, spacing: 3) {
                Text(task.title)
                    .font(.body.weight(.semibold))
                    .strikethrough(task.isCompleted)
                HStack(spacing: 8) {
                    Text(task.priority.title)
                        .font(.caption2.weight(.bold))
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(Capsule().fill(PAXTheme.accentSoft))
                    if let due = task.dueDate {
                        Text(due, style: .date)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                if !task.assignedUserName.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    Label { Text(task.assignedUserName) } icon: { PAXIcon("person.crop.circle") }
                        .font(.caption2)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            Button {
                Task { await store.toggleComplete(task, auth: auth) }
            } label: {
                Label { Text(task.isCompleted ? L10n.CommonReopen : L10n.CommonAccept) } icon: { PAXIcon("checkmark") }
            }
            .tint(.green)
        }
        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
            if auth.canAssignTeamTasks {
                Button {
                    requestDeleteTask(task)
                } label: {
                    Label { Text(L10n.CommonDelete) } icon: { PAXIcon("trash") }
                }
                .tint(.red)
            }
        }
        .contextMenu {
            Button {
                Task { await store.toggleComplete(task, auth: auth) }
            } label: {
                Label { Text(task.isCompleted ? L10n.CommonReopen : L10n.CommonAccept) } icon: { PAXIcon("checkmark") }
            }
            if auth.canAssignTeamTasks {
                Button {
                    requestDeleteTask(task)
                } label: {
                    Label { Text(L10n.CommonDelete) } icon: { PAXIcon("trash") }
                }
            }
        }
    }

    private func requestDeleteTask(_ task: PAXTaskItem) {
        PAXDelete.confirm(
            message: L10n.TaskDeleteConfirm,
            itemTitle: task.title
        ) {
            Task { await store.delete(task, auth: auth) }
        }
    }

    private func loadTeamMembers() async {
        guard auth.canAssignTeamTasks, let api = auth.api else {
            teamMembers = []
            return
        }
        if let members = try? await api.fetchPlatformTeamMembers() {
            teamMembers = members
        }
    }
}

private struct AddTaskSheet: View {
    let members: [TeamMemberRecord]
    let canAssign: Bool
    let onSave: (_ title: String, _ notes: String, _ dueDate: Date?, _ priority: PAXTaskItem.Priority, _ assignedUserId: Int) -> Void
    let onCancel: () -> Void

    @State private var title = ""
    @State private var notes = ""
    @State private var hasDueDate = false
    @State private var dueDate = Date()
    @State private var priority: PAXTaskItem.Priority = .medium
    @State private var assignedUserId = 0

    var body: some View {
        Form {
            Section(L10n.TasksAdd) {
                TextField(L10n.TasksTitleField, text: $title)
                TextField(L10n.CommonFieldNotes, text: $notes, axis: .vertical)
                    .lineLimit(2...5)
                Picker(L10n.TaskPriorityMedium, selection: $priority) {
                    ForEach(PAXTaskItem.Priority.allCases) { item in
                        Text(item.title).tag(item)
                    }
                }
            }

            Section(L10n.TasksSectionDue) {
                Toggle(L10n.TasksDueDateToggle, isOn: $hasDueDate)
                if hasDueDate {
                    DatePicker(L10n.TasksDateLabel, selection: $dueDate, displayedComponents: [.date, .hourAndMinute])
                }
            }

            if canAssign {
                Section(L10n.TasksSectionAssign) {
                    Picker(L10n.TasksTeamMember, selection: $assignedUserId) {
                        Text(L10n.TasksUnassigned).tag(0)
                        ForEach(members) { member in
                            Text(member.name).tag(member.userId)
                        }
                    }
                }
            }
        }
        .navigationTitle(L10n.TasksAdd)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button(L10n.CommonCancel, action: onCancel)
            }
            ToolbarItem(placement: .confirmationAction) {
                Button(L10n.CommonSave) {
                    let cleanTitle = title.trimmingCharacters(in: .whitespacesAndNewlines)
                    let cleanNotes = notes.trimmingCharacters(in: .whitespacesAndNewlines)
                    onSave(cleanTitle, cleanNotes, hasDueDate ? dueDate : nil, priority, assignedUserId)
                }
                .disabled(title.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
            }
        }
    }
}
