import SwiftUI

struct CustomerConversationsView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var conversations: [CustomerConversation] = []
    @State private var error: String?
    @State private var selectedSession: String?

    var body: some View {
        NavigationStack {
            Group {
                if conversations.isEmpty && error == nil {
                    ProgressView(String(localized: "Loading conversations…"))
                } else if let error {
                    ContentUnavailableView(String(localized: "Conversations unavailable"), systemImage: "message", description: Text(error))
                } else if conversations.isEmpty {
                    ContentUnavailableView(String(localized: "No conversations"), systemImage: "message", description: Text(String(localized: "Start chatting from the Chat tab.")))
                } else {
                    List(conversations) { conv in
                        NavigationLink {
                            CustomerChatDetailView(sessionID: conv.session_id)
                        } label: {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(conv.last_preview?.isEmpty == false ? conv.last_preview! : String(localized: "Conversation"))
                                    .lineLimit(2)
                                HStack {
                                    Text(conv.handler ?? "ai").font(.caption).foregroundStyle(.secondary)
                                    Spacer()
                                    if let count = conv.message_count, count > 0 {
                                        Text("\(count)").font(.caption2).foregroundStyle(.secondary)
                                    }
                                }
                            }
                        }
                    }
                }
            }
            .navigationTitle(String(localized: "Conversations"))
            .task { await load() }
            .refreshable { await load() }
        }
    }

    private func load() async {
        do {
            conversations = try await api.fetchConversations().conversations
            error = nil
        } catch {
            self.error = error.localizedDescription
        }
    }
}

struct CustomerChatDetailView: View {
    let sessionID: String
    @EnvironmentObject private var api: CustomerAPIClient

    var body: some View {
        CustomerChatView(initialSessionID: sessionID)
    }
}
