import SwiftUI

struct CalendarModuleView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var store = CalendarStore.shared
    @State private var selectedDate = Date()
    @State private var showAdd = false
    @State private var newTitle = ""
    @State private var newNotes = ""

    var body: some View {
        List {
            Section {
                DatePicker(L10n.CalendarSelectedDay, selection: $selectedDate, displayedComponents: .date)
                    .datePickerStyle(.graphical)
            }

            Section(L10n.CalendarDayEvents) {
                let dayEvents = store.events(on: selectedDate)
                if dayEvents.isEmpty {
                    Text(L10n.CalendarNoEvents)
                        .foregroundStyle(PAXTheme.textSecondary)
                } else {
                    ForEach(dayEvents) { event in
                        calendarRow(event)
                            .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                                Button {
                                    PAXDelete.confirm(
                                        message: "Dieser Termin wird dauerhaft gelöscht.",
                                        itemTitle: event.title
                                    ) {
                                        Task { await store.delete(event, auth: auth) }
                                    }
                                } label: {
                                    Label(L10n.CommonDelete, systemImage: "trash")
                                }
                                .tint(.red)
                            }
                    }
                }
            }

            Section(L10n.CalendarUpcoming) {
                ForEach(store.upcoming(limit: 8)) { event in
                    calendarRow(event)
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleCalendar)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleCalendarSettingsView() } label: {
                    Image(systemName: "slider.horizontal.3")
                }
            }
            if auth.canManageCalendar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showAdd = true } label: {
                        Image(systemName: "plus")
                    }
                }
            }
        }
        .alert(L10n.CalendarAddEvent, isPresented: $showAdd) {
            TextField(L10n.CalendarEventTitle, text: $newTitle)
            Button(L10n.CommonCancel, role: .cancel) { resetForm() }
            Button(L10n.CommonSave) { saveEvent() }
        } message: {
            Text(L10n.CalendarAddEventHint)
        }
        .refreshable {
            await PlatformSyncService.shared.sync(auth: auth)
        }
    }

    private func calendarRow(_ event: PAXCalendarEvent) -> some View {
        HStack(spacing: 12) {
            Image(systemName: event.category.systemImage)
                .foregroundStyle(.red)
                .frame(width: 28)
            VStack(alignment: .leading, spacing: 3) {
                Text(event.title).font(.subheadline.weight(.semibold))
                Text(event.notes).font(.caption).foregroundStyle(PAXTheme.textSecondary).lineLimit(1)
            }
            Spacer()
            Text(event.startDate, style: .time)
                .font(.caption)
                .foregroundStyle(PAXTheme.textTertiary)
        }
    }

    private func saveEvent() {
        let title = newTitle.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { return }
        let start = Calendar.current.date(bySettingHour: 10, minute: 0, second: 0, of: selectedDate) ?? selectedDate
        let end = Calendar.current.date(byAdding: .hour, value: 1, to: start) ?? start
        Task {
            await store.add(title: title, notes: newNotes, startDate: start, endDate: end, auth: auth)
            resetForm()
            PAXHaptics.success()
        }
    }

    private func resetForm() {
        newTitle = ""
        newNotes = ""
    }
}
