import XCTest
@testable import PAXDesignLiveChat

@MainActor
final class MessagingReliabilityTests: XCTestCase {
    func testServerAcknowledgementRemovesMatchingOptimisticMessage() {
        let clientId = UUID().uuidString.lowercased()
        let optimistic = LiveMessage(
            id: -1,
            clientMsgId: clientId,
            role: "admin",
            content: "hello"
        )
        let server = LiveMessage(
            id: 42,
            clientMsgId: clientId,
            role: "admin",
            content: "hello"
        )

        let result = MessageMerge.mergeSorted(existing: [optimistic], incoming: [server])

        XCTAssertTrue(result.changed)
        XCTAssertEqual(result.messages, [server])
    }

    func testVoiceAttachmentFieldsSurviveClientIdMerge() {
        let clientId = UUID().uuidString.lowercased()
        let optimistic = LiveMessage(
            id: -99,
            clientMsgId: clientId,
            role: "admin",
            content: "",
            attachmentType: "voice",
            audioUrl: "pending://\(clientId)",
            audioDuration: 12.4
        )
        let server = LiveMessage(
            id: 77,
            clientMsgId: clientId,
            role: "admin",
            content: "",
            attachmentType: "voice",
            audioUrl: "https://paxdesign.at/wp-content/uploads/voice.m4a",
            audioDuration: 12.4
        )

        let result = MessageMerge.mergeSorted(existing: [optimistic], incoming: [server])

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.id, 77)
        XCTAssertEqual(result.messages.first?.attachmentType, "voice")
        XCTAssertEqual(result.messages.first?.audioUrl, server.audioUrl)
        XCTAssertEqual(result.messages.first?.audioDuration, 12.4)
    }

    func testLocationAttachmentFieldsSurviveClientIdMerge() {
        let clientId = UUID().uuidString.lowercased()
        let optimistic = LiveMessage(
            id: -12,
            clientMsgId: clientId,
            role: "admin",
            content: "Office",
            attachmentType: "location",
            locationLat: 48.2082,
            locationLng: 16.3738,
            locationLabel: "Office"
        )
        let server = LiveMessage(
            id: 88,
            clientMsgId: clientId,
            role: "admin",
            content: "Office",
            attachmentType: "location",
            locationLat: 48.2082,
            locationLng: 16.3738,
            locationLabel: "Office"
        )

        let result = MessageMerge.mergeSorted(existing: [optimistic], incoming: [server])

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.locationLat, 48.2082)
        XCTAssertEqual(result.messages.first?.locationLng, 16.3738)
        XCTAssertEqual(result.messages.first?.locationLabel, "Office")
    }

    func testDuplicateIngressNeverCreatesDuplicateIDs() {
        let message = LiveMessage(id: 8, role: "user", content: "once")
        let existing = [message, message]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: [message])

        XCTAssertEqual(result.messages.map(\.id), [8])
    }

    func testReactionsCannotCrashOnLegacyDuplicateIDs() {
        let first = LiveMessage(id: 8, role: "admin", content: "once")
        let duplicate = LiveMessage(id: 8, role: "admin", content: "once")

        let result = MessageMerge.applyReactions(
            to: [first, duplicate],
            reactions: ["8": "like"]
        )

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.reaction, "like")
    }

    func testOutOfOrderMessagesAreSortedByServerSequence() {
        let existing = [LiveMessage(id: 3, role: "user", content: "three")]
        let incoming = [
            LiveMessage(id: 2, role: "user", content: "two"),
            LiveMessage(id: 4, role: "assistant", content: "four"),
        ]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: incoming)

        XCTAssertEqual(result.messages.map(\.id), [2, 3, 4])
    }

    func testOptimisticMessagesSortAfterServerHistory() {
        let server = [
            LiveMessage(id: 10, role: "user", content: "ten", ts: 100),
            LiveMessage(id: 11, role: "admin", content: "eleven", ts: 110),
        ]
        let optimistic = LiveMessage(
            id: -42,
            clientMsgId: UUID().uuidString.lowercased(),
            role: "admin",
            content: "pending",
            ts: 120
        )

        let result = MessageMerge.mergeSorted(existing: server, incoming: [optimistic])

        XCTAssertEqual(result.messages.map(\.id), [10, 11, -42])
    }

    @MainActor
    func testForcedUnreadSurvivesAdminLastReply() {
        let settings = AppSettingsStore.shared
        let session = LiveSession(
            id: 6,
            sessionId: "pax_forced_unread",
            handler: "admin",
            handlerLabel: "Admin",
            adminName: "Admin",
            customerName: "Customer",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 3,
            seq: 3,
            lastPreview: "Reply",
            lastRole: "admin"
        )

        settings.markSessionRead(session.sessionId, seq: 3)
        XCTAssertFalse(settings.isSessionUnread(session))

        settings.markSessionUnread(session.sessionId)
        XCTAssertTrue(settings.isSessionUnread(session))
    }

    func testSSEParserSupportsDurableChannelAndMultilineData() {
        var buffer = ""
        XCTAssertNil(ChatEventStreamParser.parseLine(
            "data: {\"id\":91,\"type\":\"message\",",
            dataBuffer: &buffer
        ))
        XCTAssertNil(ChatEventStreamParser.parseLine(
            "data: \"channel\":\"session:pax_1\",\"payload\":{\"seq\":7}}",
            dataBuffer: &buffer
        ))
        let event = ChatEventStreamParser.parseLine("", dataBuffer: &buffer)

        XCTAssertEqual(event?.id, 91)
        XCTAssertEqual(event?.channel, "session:pax_1")
        XCTAssertEqual(StreamPayload.int(event?.payload["seq"]), 7)
    }

    func testLiveMessageDecodesLinkScanAndLinkCardFields() throws {
        let json = """
        {
          "id": 12,
          "role": "user",
          "content": "See https://example.com/page",
          "link_scan_status": "safe",
          "link_scan_urls": "[{\\"url\\":\\"https://example.com/page\\",\\"status\\":\\"safe\\"}]"
        }
        """.data(using: .utf8)!
        let message = try JSONDecoder().decode(LiveMessage.self, from: json)
        XCTAssertEqual(message.linkScanStatus, "safe")
        XCTAssertTrue(message.showsLinkScanBadge)
    }

    func testSiteScopeKeyIsStable() {
        XCTAssertEqual(
            SiteScopeKey.make("https://example.com/wp-json"),
            SiteScopeKey.make("https://example.com/wp-json")
        )
        XCTAssertNotEqual(
            SiteScopeKey.make("https://example.com/wp-json"),
            SiteScopeKey.make("https://other.example/wp-json")
        )
    }

    func testPollCursorUsesInclusiveSinceSemantics() {
        // Server poll returns msg_seq > since. Advancing since to the latest seq
        // before the message is merged locally would skip that message entirely.
        let existing = [LiveMessage(id: 4, role: "user", content: "four")]
        let incoming = [LiveMessage(id: 5, role: "user", content: "five")]

        let result = MessageMerge.mergeSorted(existing: existing, incoming: incoming)

        XCTAssertTrue(result.changed)
        XCTAssertEqual(result.messages.map(\.id), [4, 5])

        let localMax = result.messages.map(\.id).max() ?? 0
        let serverSeq = 5
        let pollSeq = localMax < serverSeq ? localMax : max(localMax, serverSeq)
        XCTAssertEqual(pollSeq, 4, "Poll cursor must stay behind server seq until message 5 is local")
    }

    func testLinkScanStatusLabelsMatchProductionContract() {
        XCTAssertEqual(LinkScanStatus(raw: "checking").label, L10n.ChatLinkScanChecking)
        XCTAssertEqual(LinkScanStatus(raw: "safe").label, L10n.ChatLinkScanSafe)
        XCTAssertEqual(LinkScanStatus(raw: "suspicious").label, L10n.ChatLinkScanSuspicious)
        XCTAssertEqual(LinkScanStatus(raw: "dangerous").label, L10n.ChatLinkScanDangerous)
        XCTAssertEqual(LinkScanStatus(raw: "failed").label, L10n.ChatLinkScanIncomplete)
        XCTAssertEqual(LinkScanStatus(raw: "timeout").label, L10n.ChatLinkScanIncomplete)
        XCTAssertEqual(LinkScanStatus(raw: "incomplete").label, L10n.ChatLinkScanIncomplete)
    }

    func testLinkScanSupportDefaultsToCheckingUntilServerResult() {
        let message = LiveMessage(id: 3, role: "user", content: "See https://example.com")
        XCTAssertEqual(LinkScanSupport.resolvedStatus(for: message), .checking)
        XCTAssertFalse(LinkScanSupport.shouldBlockLinks(in: message))
    }

    func testLinkScanSupportBlocksDangerousLinksOnlyAfterServerVerdict() {
        let dangerous = LiveMessage(id: 4, role: "user", content: "bad", linkScanStatus: "dangerous")
        XCTAssertTrue(LinkScanSupport.shouldBlockLinks(in: dangerous))
    }

    func testStreamPayloadDecodesInlineWebsiteMessage() {
        let payload: [String: Any] = [
            "session_id": "pax_test",
            "seq": 12,
            "role": "user",
            "message": [
                "id": 12,
                "client_msg_id": "abc-123",
                "role": "user",
                "content": "Hello from website",
                "ts": 1_720_000_000,
            ],
        ]

        let decoded = StreamPayload.messages(from: payload)

        XCTAssertEqual(decoded.count, 1)
        XCTAssertEqual(decoded.first?.id, 12)
        XCTAssertEqual(decoded.first?.content, "Hello from website")
        XCTAssertEqual(decoded.first?.clientMsgId, "abc-123")
    }

    func testConversationSyncCooldownBlocksRapidRepeats() {
        ConversationSyncCoordinator.reset()
        XCTAssertTrue(ConversationSyncCoordinator.shouldRunFullSync())
        ConversationSyncCoordinator.endFullSync()
        XCTAssertFalse(ConversationSyncCoordinator.shouldRunFullSync())
    }

    func testCoalescedReadsAllowParallelInflight() async {
        await NetworkCircuitBreaker.shared.reset()
        do {
            try await NetworkCircuitBreaker.shared.recordRequestStart(
                endpoint: "team-contacts",
                method: "GET"
            )
            try await NetworkCircuitBreaker.shared.recordRequestStart(
                endpoint: "team-contacts",
                method: "GET"
            )
        } catch {
            XCTFail("Unexpected throw: \(error)")
        }
        await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "team-contacts")
        await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "team-contacts")
    }

    func testOpenChatPollingIntervalsAreConservative() {
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 0, openChat: true)
        XCTAssertEqual(AppRefreshPolicy.chatThreadIntervalLive, 5_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamThreadIntervalLive, 5_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.sessionListInterval, 3_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamListInterval, 3_000_000_000)
    }

    func testIdlePollingIntervalsReducedVersusAggressiveBaseline() {
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 0, openChat: false)
        XCTAssertGreaterThanOrEqual(AppRefreshPolicy.sessionListInterval, 6_000_000_000)
        XCTAssertGreaterThanOrEqual(AppRefreshPolicy.teamListInterval, 6_000_000_000)
    }

    func testNetworkRequestTrackerCountsEndpoints() async {
        await NetworkRequestTracker.shared.reset()
        await NetworkRequestTracker.shared.record(endpoint: "sessions")
        await NetworkRequestTracker.shared.record(endpoint: "sessions")
        await NetworkRequestTracker.shared.record(endpoint: "poll:test")
        XCTAssertEqual(await NetworkRequestTracker.shared.totalInWindow, 3)
        XCTAssertEqual(await NetworkRequestTracker.shared.snapshot()["sessions"], 2)
    }

    func testCircuitBreakerOpensOnEdge403() async {
        await NetworkCircuitBreaker.shared.reset()
        await NetworkCircuitBreaker.shared.recordHTTPResponse(
            status: 403,
            bodySnippet: "Access to this resource on the server is denied!",
            endpoint: "sessions",
            retryAfter: nil
        )
        XCTAssertTrue(await NetworkCircuitBreaker.shared.isOpen)
        XCTAssertTrue(await NetworkCircuitBreaker.shared.pollingSuspended)
    }

    func testCircuitBreakerRateCapBlocksBurst() async {
        await NetworkCircuitBreaker.shared.reset()
        await MainActor.run {
            NetworkCircuitBreaker.shared.maxRequestsPerSecond = 2
        }
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "a")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "a")
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "b")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "b")
        do {
            try await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "c")
            XCTFail("Expected rate cap")
        } catch NetworkCircuitBreakerError.rateLimited {
            XCTAssertTrue(true)
        } catch {
            XCTFail("Unexpected error \(error)")
        }
    }

    func testDeviceEndpointsBypassCircuitRateCap() async {
        await NetworkCircuitBreaker.shared.reset()
        await MainActor.run {
            NetworkCircuitBreaker.shared.maxRequestsPerSecond = 1
        }
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "sessions")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "sessions")
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "devices-list"))
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "devices-list")
    }

    func testSuggestionReadsBypassInflightDedup() async {
        await NetworkCircuitBreaker.shared.reset()
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(
            endpoint: "suggestions:pax_test:12",
            method: "GET"
        ))
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(
            endpoint: "suggestions:pax_test:12",
            method: "GET"
        ))
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "suggestions:pax_test:12")
    }

    func testTeamMessagingEndpointsBypassClientRateCap() async {
        await NetworkCircuitBreaker.shared.reset()
        await MainActor.run {
            NetworkCircuitBreaker.shared.maxRequestsPerSecond = 1
        }
        try? await NetworkCircuitBreaker.shared.recordRequestStart(endpoint: "sessions", method: "GET")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "sessions")
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(
            endpoint: "team-poll:team_1_2",
            method: "GET"
        ))
        XCTAssertNoThrow(try await NetworkCircuitBreaker.shared.recordRequestStart(
            endpoint: "team-send",
            method: "POST"
        ))
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "team-poll:team_1_2")
        try? await NetworkCircuitBreaker.shared.recordRequestEnd(endpoint: "team-send")
    }

    func testApplication429DoesNotOpenCircuit() async {
        await NetworkCircuitBreaker.shared.reset()
        await NetworkCircuitBreaker.shared.recordHTTPResponse(
            status: 429,
            bodySnippet: "{\"code\":\"rate_limit\",\"message\":\"Too many requests.\",\"data\":{\"status\":429}}",
            endpoint: "team-send",
            retryAfter: "30"
        )
        XCTAssertFalse(await NetworkCircuitBreaker.shared.isOpen)
    }

    func testLiveChatAPIErrorRateLimitIsTransient() {
        let err = LiveChatAPIError.rateLimited("Too many requests.")
        XCTAssertTrue(err.isTransientSendFailure)
        let rejected = LiveChatAPIError.rejected("Zu viele Anfragen. Bitte warten.")
        XCTAssertTrue(rejected.isTransientSendFailure)
        let validation = LiveChatAPIError.rejected("Message cannot be empty")
        XCTAssertFalse(validation.isTransientSendFailure)
    }

    func testPollResponseEmptySnapshotDecodes() {
        XCTAssertEqual(PollResponse.emptySnapshot.handler, "ai")
        XCTAssertTrue(PollResponse.emptySnapshot.messages.isEmpty)
    }

    func testChatThreadRegistryRetainsActiveSessionDuringEviction() async {
        AppRefreshPolicy.setActiveSession("team_1_2")
        defer { AppRefreshPolicy.setActiveSession(nil) }

        let registry = ChatThreadRegistry.shared
        registry.clearAll()
        _ = registry.teamThread(sessionId: "team_1_2")

        for index in 0..<305 {
            _ = registry.teamThread(sessionId: "team_\(index)_999")
        }

        let active = registry.teamThread(sessionId: "team_1_2")
        XCTAssertEqual(active.sessionId, "team_1_2")
    }

    func testRoleLabelFormatterMapsFeminineExecutiveTitle() {
        XCTAssertEqual(RoleLabelFormatter.localized("Geschäftsführerin"), L10n.RoleExecutiveDirector)
        XCTAssertEqual(RoleLabelFormatter.localized("Executive Director"), L10n.RoleExecutiveDirector)
        XCTAssertEqual(RoleLabelFormatter.localized("Administrator"), L10n.RoleAdministrator)
    }

    func testTeamConversationIdIsStableRegardlessOfCallerOrder() {
        XCTAssertEqual(
            TeamVoiceRecorderService.teamConversationId(currentUserId: 12, otherUserId: 34),
            "team_12_34"
        )
        XCTAssertEqual(
            TeamVoiceRecorderService.teamConversationId(currentUserId: 34, otherUserId: 12),
            "team_12_34"
        )
    }

    func testAPNsPermanentFailureDetection() {
        XCTAssertTrue(APNsRegistrationPolicy.isPermanentFailureMessage(
            "NSCocoaErrorDomain (3000): No valid 'aps-environment' entitlement string found for application."
        ))
        XCTAssertFalse(APNsRegistrationPolicy.isPermanentFailureMessage("network timeout"))
    }

    func testSSEHealthySuspendsListPolling() {
        AppRefreshPolicy.sseHealthy = true
        AppRefreshPolicy.update(scenePhase: .active)
        AppRefreshPolicy.update(liveCount: 2, openChat: true)
        XCTAssertEqual(AppRefreshPolicy.sessionListInterval, 60_000_000_000)
        XCTAssertEqual(AppRefreshPolicy.teamListInterval, 60_000_000_000)
        AppRefreshPolicy.sseHealthy = false
    }

    func testPushRegistrationCoordinatorEnforcesAutomaticSpacing() async {
        PushRegistrationCoordinator.reset()
        XCTAssertTrue(PushRegistrationCoordinator.shouldAttemptAutomaticApns(reason: .login))
        PushRegistrationCoordinator.noteAutomaticApnsAttempt()
        XCTAssertFalse(PushRegistrationCoordinator.shouldAttemptAutomaticApns(reason: .foreground))
        XCTAssertFalse(PushRegistrationCoordinator.shouldAttemptAutomaticApns(reason: .heartbeat))
        XCTAssertTrue(PushRegistrationCoordinator.shouldAttemptAutomaticApns(reason: .manualRepair))
        XCTAssertTrue(PushRegistrationCoordinator.shouldAttemptAutomaticApns(reason: .tokenReceived))
    }

    func testReleaseBuildWithoutSignedEntitlementIsBlocked() {
        #if !DEBUG
        // Release builds without PAXSignedAPSEnvironment mirror must not claim push entitlement.
        if Bundle.main.infoDictionary?["PAXSignedAPSEnvironment"] == nil {
            XCTAssertFalse(PAXAPNsEnvironment.hasPushEntitlement)
        }
        #endif
    }

    func testSystemNoticeRetryCollapsesToSingleMessage() {
        let first = LiveMessage(
            id: 1,
            clientMsgId: "sys:chat_closed",
            role: "system",
            content: "Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten."
        )
        let retry = LiveMessage(
            id: 2,
            clientMsgId: "sys:chat_closed",
            role: "system",
            content: "Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten."
        )

        let result = MessageMerge.mergeSorted(existing: [first], incoming: [retry])

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.id, 1)
    }

    @MainActor
    func testUnreadMessageCountUsesMessageDeltaNotConversationCount() {
        let settings = AppSettingsStore.shared
        let session = LiveSession(
            id: 1,
            sessionId: "pax_badge_test",
            handler: "admin",
            handlerLabel: "Admin",
            adminName: "Admin",
            customerName: "Customer",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 5,
            seq: 5,
            lastPreview: "Hello",
            lastRole: "user"
        )

        XCTAssertEqual(settings.unreadMessageCount(for: session), 5)

        settings.markSessionRead(session.sessionId, seq: 3)
        XCTAssertEqual(settings.unreadMessageCount(for: session), 2)

        settings.markSessionRead(session.sessionId, seq: 5)
        XCTAssertEqual(settings.unreadMessageCount(for: session), 0)
    }

    @MainActor
    func testUnreadMessageCountIgnoresClosedAndAdminLastMessageSessions() {
        let settings = AppSettingsStore.shared
        let closed = LiveSession(
            id: 2,
            sessionId: "pax_closed",
            handler: "closed",
            handlerLabel: "Closed",
            adminName: "Admin",
            customerName: "Customer",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 4,
            seq: 4,
            lastPreview: "Bye",
            lastRole: "user"
        )
        let replied = LiveSession(
            id: 3,
            sessionId: "pax_replied",
            handler: "admin",
            handlerLabel: "Admin",
            adminName: "Admin",
            customerName: "Customer",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 6,
            seq: 6,
            lastPreview: "Reply",
            lastRole: "admin"
        )

        XCTAssertEqual(settings.unreadMessageCount(for: closed), 0)
        XCTAssertEqual(settings.unreadMessageCount(for: replied), 0)
    }

    @MainActor
    func testTeamUnreadMessageCountCountsIncomingOnly() {
        let settings = AppSettingsStore.shared
        let incoming = LiveSession(
            id: 4,
            sessionId: "team_12_34",
            handler: "team_dm",
            handlerLabel: "Team",
            adminName: "",
            customerName: "Alex",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 3,
            seq: 3,
            lastPreview: "Ping",
            lastRole: "user",
            otherUserId: 34
        )
        let outgoing = LiveSession(
            id: 5,
            sessionId: "team_12_56",
            handler: "team_dm",
            handlerLabel: "Team",
            adminName: "",
            customerName: "Sam",
            sessionRating: 0,
            detectedService: "",
            updatedAt: "",
            messageCount: 2,
            seq: 2,
            lastPreview: "Sent",
            lastRole: "admin",
            otherUserId: 56
        )

        XCTAssertEqual(settings.unreadMessageCount(for: incoming), 3)
        XCTAssertEqual(settings.unreadMessageCount(for: outgoing), 0)
    }

    func testLegacySystemDuplicateWithoutClientIdCollapses() {
        let first = LiveMessage(
            id: 1,
            role: "system",
            content: "Chat-Session gestartet."
        )
        let duplicate = LiveMessage(
            id: 4,
            role: "system",
            content: "Chat-Session gestartet."
        )

        let result = MessageMerge.mergeSorted(existing: [first], incoming: [duplicate])

        XCTAssertEqual(result.messages.count, 1)
        XCTAssertEqual(result.messages.first?.id, 1)
    }

    func testPlatformDashboardDecodesAnalyticsSeries() throws {
        let json = """
        {
          "sessions_total": 12,
          "live_count": 2,
          "open_tasks": 4,
          "overdue_tasks": 1,
          "upcoming_events": 3,
          "activity_chart": [{"label":"2026-07-12","value":2}],
          "activity_series": [{
            "label":"2026-07-12",
            "sessions":2,
            "messages":8,
            "live_requests":1,
            "team_messages":3
          }],
          "trends": {
            "sessions_pct": 12.5,
            "messages_pct": -4.0,
            "live_requests_pct": 0
          },
          "category_totals": [
            {"label":"live","value":2},
            {"label":"active","value":8}
          ]
        }
        """.data(using: .utf8)!
        let payload = try JSONDecoder().decode(PlatformDashboardPayload.self, from: json)
        XCTAssertEqual(payload.activitySeries.count, 1)
        XCTAssertEqual(payload.activitySeries.first?.messages, 8)
        XCTAssertEqual(payload.trends.messagesPct, -4.0)
        XCTAssertEqual(payload.categoryTotals.count, 2)
    }
}
