import SwiftUI

struct TasksModuleView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var store = TaskStore.shared
    @State private var showAdd = false
    @State private var newTitle = ""
    @State private var filter: TaskFilter = .open

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
        switch filter {
        case .open: return store.tasks.filter { !$0.isCompleted }
        case .completed: return store.tasks.filter(\.isCompleted)
        case .all: return store.tasks
        }
    }

    var body: some View {
        List {
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
                    .onDelete { indexSet in
                        Task {
                            for task in indexSet.map({ filteredTasks[$0] }) {
                                await store.delete(task, auth: auth)
                            }
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
                    Image(systemName: "slider.horizontal.3")
                }
            }
            if auth.canManageTasks {
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showAdd = true } label: {
                        Image(systemName: "plus")
                    }
                }
            }
        }
        .alert(L10n.TasksAdd, isPresented: $showAdd) {
            TextField(L10n.TasksTitleField, text: $newTitle)
            Button(L10n.CommonCancel, role: .cancel) { newTitle = "" }
            Button(L10n.CommonSave) {
                let title = newTitle.trimmingCharacters(in: .whitespacesAndNewlines)
                guard !title.isEmpty else { return }
                Task {
                    await store.add(title: title, auth: auth)
                    newTitle = ""
                    PAXHaptics.success()
                }
            }
        }
        .refreshable {
            await PlatformSyncService.shared.sync(auth: auth)
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
                Image(systemName: task.isCompleted ? "checkmark.circle.fill" : "circle")
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
            }
        }
        .swipeActions(edge: .leading, allowsFullSwipe: true) {
            Button {
                Task { await store.toggleComplete(task, auth: auth) }
            } label: {
                Label(task.isCompleted ? L10n.CommonReopen : L10n.CommonAccept, systemImage: "checkmark")
            }
            .tint(.green)
        }
        .contextMenu {
            Button {
                Task { await store.toggleComplete(task, auth: auth) }
            } label: {
                Label(task.isCompleted ? L10n.CommonReopen : L10n.CommonAccept, systemImage: "checkmark")
            }
            if auth.canManageTasks {
                Button(role: .destructive) {
                    Task { await store.delete(task, auth: auth) }
                } label: {
                    Label(L10n.CommonDelete, systemImage: "trash")
                }
            }
        }
    }
}
