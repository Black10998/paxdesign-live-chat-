import SwiftUI
import PhotosUI
import UniformTypeIdentifiers

@MainActor
final class CustomerCybercrimeDraft: ObservableObject {
    enum Step: Int, CaseIterable {
        case identity, incident, evidence, review
        var title: String {
            switch self {
            case .identity: return String(localized: "Identity")
            case .incident: return String(localized: "Incident")
            case .evidence: return String(localized: "Evidence")
            case .review: return String(localized: "Review")
            }
        }
    }

    @Published var step: Step = .identity
    @Published var fullName = ""
    @Published var email = ""
    @Published var phoneLocal = ""
    @Published var countryCode = "AT"
    @Published var identityAccuracy = false
    @Published var identityFile: CustomerCybercrimeUpload?
    @Published var category = "phishing_fraud"
    @Published var incidentDate = Date()
    @Published var includeTime = true
    @Published var selectedPlatforms: Set<String> = []
    @Published var platformsOther = ""
    @Published var description = ""
    @Published var financialLoss = ""
    @Published var currency = "EUR"
    @Published var urgency = "medium"
    @Published var evidence: [CustomerCybercrimeUpload] = []
    @Published var declTruthful = false
    @Published var declFalseReports = false
    @Published var declVerification = false
    @Published var error: String?
    @Published var isSubmitting = false

    var country: CustomerCybercrimeCatalog.Country {
        CustomerCybercrimeCatalog.countries.first { $0.id == countryCode }
            ?? CustomerCybercrimeCatalog.Country(id: countryCode, name: countryCode, dial: "+")
    }

    var platformsText: String {
        let picked = CustomerCybercrimeCatalog.platforms.filter { selectedPlatforms.contains($0) }
        let extra = platformsOther.trimmingCharacters(in: .whitespacesAndNewlines)
        return (picked + (extra.isEmpty ? [] : [extra])).joined(separator: ", ")
    }

    var canContinueIdentity: Bool {
        fullName.trimmingCharacters(in: .whitespacesAndNewlines).count >= 2
            && email.contains("@")
            && phoneLocal.filter(\.isNumber).count >= 6
            && identityFile != nil
            && identityAccuracy
    }

    var canContinueIncident: Bool {
        !category.isEmpty
            && platformsText.count >= 2
            && description.trimmingCharacters(in: .whitespacesAndNewlines).count >= 20
    }

    var canSubmit: Bool {
        canContinueIdentity && canContinueIncident && declTruthful && declFalseReports && declVerification
    }

    func prefill(from profile: CustomerProfileResponse.Profile?) {
        if fullName.isEmpty { fullName = profile?.display_name ?? "" }
        if email.isEmpty { email = profile?.email ?? "" }
    }

    func fields(chatSessionID: String?) -> [String: String] {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        let date = formatter.string(from: incidentDate)
        formatter.dateFormat = "HH:mm"
        let time = includeTime ? formatter.string(from: incidentDate) : "00:00"
        var fields: [String: String] = [
            "full_name": fullName.trimmingCharacters(in: .whitespacesAndNewlines),
            "email": email.trimmingCharacters(in: .whitespacesAndNewlines),
            "phone_country_code": country.dial,
            "phone_local": phoneLocal,
            "phone": "\(country.dial) \(phoneLocal)",
            "country": countryCode,
            "identity_accuracy": "1",
            "category": category,
            "incident_date": date,
            "incident_time": time,
            "platforms": platformsText,
            "description": description.trimmingCharacters(in: .whitespacesAndNewlines),
            "financial_loss": financialLoss,
            "financial_currency": currency,
            "urgency": urgency,
            "decl_truthful": "1",
            "decl_false_reports": "1",
            "decl_verification": "1",
            "locale": CustomerCybercrimeCatalog.localeCode(),
            "source": "ios",
        ]
        if let chatSessionID, !chatSessionID.isEmpty {
            fields["chat_session_id"] = chatSessionID
        }
        return fields
    }

    func allFiles() -> [CustomerCybercrimeUpload] {
        var files = evidence
        if let identityFile {
            files.insert(identityFile, at: 0)
        }
        return files
    }
}

struct CustomerCybercrimeWizardView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @Environment(\.dismiss) private var dismiss
    @StateObject private var draft = CustomerCybercrimeDraft()
    var onFinished: () -> Void

    var body: some View {
        VStack(spacing: 0) {
            progress
            ScrollView {
                Group {
                    switch draft.step {
                    case .identity: identityStep
                    case .incident: incidentStep
                    case .evidence: evidenceStep
                    case .review: reviewStep
                    }
                }
                .padding(PAXSpacing.screenHorizontal)
                .padding(.vertical, 20)
            }
            footer
        }
        .background(PAXTheme.background.ignoresSafeArea())
        .navigationTitle(draft.step.title)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button(String(localized: "Close")) { dismiss() }
            }
        }
        .onAppear {
            draft.prefill(from: auth.profile)
            if let region = Locale.current.regionCode, region.count == 2 {
                if draft.countryCode == "AT", CustomerCybercrimeCatalog.countries.contains(where: { $0.id == region }) {
                    draft.countryCode = region
                }
            }
        }
        .alert(String(localized: "Could not submit"), isPresented: Binding(
            get: { draft.error != nil },
            set: { if !$0 { draft.error = nil } }
        )) {
            Button(String(localized: "OK"), role: .cancel) { draft.error = nil }
        } message: {
            Text(draft.error ?? "")
        }
    }

    private var progress: some View {
        VStack(spacing: 8) {
            HStack(spacing: 6) {
                ForEach(CustomerCybercrimeDraft.Step.allCases, id: \.rawValue) { step in
                    Capsule()
                        .fill(step.rawValue <= draft.step.rawValue ? PAXTheme.accent : PAXTheme.divider)
                        .frame(height: 4)
                }
            }
            Text(String(localized: "Step \(draft.step.rawValue + 1) of 4"))
                .font(PAXTypography.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.top, 12)
        .padding(.bottom, 8)
    }

    private var identityStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionIntro(
                String(localized: "Identity & verification"),
                String(localized: "Enter your legal details as shown on official documents.")
            )
            PAXRevolutField(title: String(localized: "Full legal name"), systemImage: "person", text: $draft.fullName)
            PAXRevolutField(title: String(localized: "Email address"), systemImage: "envelope", text: $draft.email, keyboardType: .emailAddress, textContentType: .emailAddress)
            countryPicker
            phoneRow
            identityUpload
            Toggle(String(localized: "I confirm that the identity information I provided is accurate and correct."), isOn: $draft.identityAccuracy)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textPrimary)
                .tint(PAXTheme.accent)
        }
    }

    private var countryPicker: some View {
        Menu {
            ForEach(CustomerCybercrimeCatalog.countries) { country in
                Button("\(country.flag) \(country.name)") { draft.countryCode = country.id }
            }
        } label: {
            HStack {
                PAXIcon("globe", size: .row, emphasis: .secondary)
                Text("\(draft.country.flag) \(draft.country.name)")
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textPrimary)
                Spacer()
                PAXIcon("chevron.up.chevron.down", size: .inline, emphasis: .tertiary)
            }
            .padding(.horizontal, 16)
            .frame(minHeight: PAXSpacing.inputHeight)
            .background(PAXTheme.surface, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 12, style: .continuous).strokeBorder(PAXTheme.borderSubtle, lineWidth: 1))
        }
    }

    private var phoneRow: some View {
        HStack(spacing: 10) {
            Text(draft.country.dial.isEmpty ? "+" : draft.country.dial)
                .font(PAXTypography.body.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)
                .padding(.horizontal, 12)
                .frame(minHeight: PAXSpacing.inputHeight)
                .background(PAXTheme.surface, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
            PAXRevolutField(title: String(localized: "Phone number"), systemImage: "phone", text: $draft.phoneLocal, keyboardType: .phonePad, textContentType: .telephoneNumber)
        }
    }

    private var identityUpload: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(String(localized: "Identity document"))
                .font(PAXTypography.rowTitle)
                .foregroundStyle(PAXTheme.textPrimary)
            Text(String(localized: "PDF or image — passport, ID card, or driver’s license. Required."))
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
            CustomerCybercrimeFileDrop(
                title: draft.identityFile?.filename ?? String(localized: "Upload identity document"),
                icon: "person.text.rectangle",
                allowsMultiple: false,
                onFiles: { files in
                    if let first = files.first {
                        draft.identityFile = CustomerCybercrimeUpload(
                            field: "identity_document",
                            filename: first.filename,
                            mime: first.mime,
                            data: first.data
                        )
                    }
                }
            )
        }
    }

    private var incidentStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionIntro(
                String(localized: "Incident information"),
                String(localized: "Describe what happened accurately. Clear details speed up assessment.")
            )
            Text(String(localized: "Incident category"))
                .font(PAXTypography.rowTitle)
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 10), GridItem(.flexible(), spacing: 10)], spacing: 10) {
                ForEach(CustomerCybercrimeCatalog.categories) { item in
                    Button {
                        draft.category = item.id
                        PAXHaptics.light()
                    } label: {
                        VStack(alignment: .leading, spacing: 8) {
                            PAXIcon(item.icon, size: .card, tint: draft.category == item.id ? PAXTheme.accent : PAXTheme.textSecondary)
                            Text(item.title)
                                .font(PAXTypography.meta.weight(.semibold))
                                .foregroundStyle(PAXTheme.textPrimary)
                                .multilineTextAlignment(.leading)
                                .fixedSize(horizontal: false, vertical: true)
                        }
                        .padding(12)
                        .frame(maxWidth: .infinity, minHeight: 88, alignment: .topLeading)
                        .background(draft.category == item.id ? PAXTheme.accent.opacity(0.12) : PAXTheme.surface)
                        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                        .overlay(
                            RoundedRectangle(cornerRadius: 14, style: .continuous)
                                .strokeBorder(draft.category == item.id ? PAXTheme.accent : PAXTheme.divider, lineWidth: 1)
                        )
                    }
                    .buttonStyle(PAXRevolutPressableStyle())
                }
            }
            DatePicker(String(localized: "Incident date"), selection: $draft.incidentDate, displayedComponents: draft.includeTime ? [.date, .hourAndMinute] : [.date])
                .font(PAXTypography.body)
            Toggle(String(localized: "Include approximate time"), isOn: $draft.includeTime)
                .font(PAXTypography.meta)
                .tint(PAXTheme.accent)
            Text(String(localized: "Affected platforms or services"))
                .font(PAXTypography.rowTitle)
            FlexiblePlatformChips(selection: $draft.selectedPlatforms)
            PAXRevolutField(title: String(localized: "Other platforms"), systemImage: "plus", text: $draft.platformsOther)
            VStack(alignment: .leading, spacing: 8) {
                Text(String(localized: "Detailed incident description"))
                    .font(PAXTypography.rowTitle)
                TextEditor(text: $draft.description)
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .frame(minHeight: 140)
                    .padding(12)
                    .background(PAXTheme.surface, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
                    .overlay(RoundedRectangle(cornerRadius: 12, style: .continuous).strokeBorder(PAXTheme.borderSubtle, lineWidth: 1))
            }
            PAXRevolutField(title: String(localized: "Estimated financial loss"), systemImage: "banknote", text: $draft.financialLoss, keyboardType: .decimalPad)
            Picker(String(localized: "Currency"), selection: $draft.currency) {
                Text("EUR").tag("EUR")
                Text("USD").tag("USD")
                Text("GBP").tag("GBP")
                Text("CHF").tag("CHF")
                Text("AED").tag("AED")
            }
            .pickerStyle(.segmented)
            Text(String(localized: "Urgency level"))
                .font(PAXTypography.rowTitle)
            ForEach(CustomerCybercrimeCatalog.urgencyLevels) { item in
                Button {
                    draft.urgency = item.id
                } label: {
                    HStack {
                        Circle().fill(item.tint).frame(width: 8, height: 8)
                        Text(item.title)
                            .font(PAXTypography.body)
                            .foregroundStyle(PAXTheme.textPrimary)
                        Spacer()
                        if draft.urgency == item.id {
                            PAXIcon("checkmark.circle.fill", size: .row, tint: PAXTheme.accent)
                        }
                    }
                    .padding(14)
                    .paxRevolutSurface(cornerRadius: 14, elevation: 0)
                }
                .buttonStyle(PAXRevolutPressableStyle())
            }
        }
    }

    private var evidenceStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionIntro(
                String(localized: "Upload evidence"),
                String(localized: "Attach screenshots, documents, or supporting files. Maximum 25 MB per file.")
            )
            evidenceBlock(title: String(localized: "Screenshots"), field: "evidence_screenshots[]", icon: "photo.on.rectangle")
            evidenceBlock(title: String(localized: "Documents & files"), field: "evidence_documents[]", icon: "doc")
            evidenceBlock(title: String(localized: "Chat exports"), field: "evidence_chats[]", icon: "bubble.left.and.bubble.right")
            evidenceBlock(title: String(localized: "Additional evidence"), field: "evidence_other[]", icon: "paperclip")
        }
    }

    private func evidenceBlock(title: String, field: String, icon: String) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title)
                .font(PAXTypography.rowTitle)
            CustomerCybercrimeFileDrop(title: String(localized: "Add files"), icon: icon, allowsMultiple: true) { files in
                let remaining = CustomerCybercrimeCatalog.maxFiles - draft.allFiles().count
                for file in files.prefix(max(0, remaining)) {
                    draft.evidence.append(
                        CustomerCybercrimeUpload(field: field, filename: file.filename, mime: file.mime, data: file.data)
                    )
                }
            }
            ForEach(draft.evidence.filter { $0.field == field }) { file in
                HStack {
                    PAXIcon(icon, size: .row, emphasis: .secondary)
                    Text(file.filename)
                        .font(PAXTypography.meta)
                        .lineLimit(1)
                    Spacer()
                    Button {
                        draft.evidence.removeAll { $0.id == file.id }
                    } label: {
                        PAXIcon("xmark.circle.fill", size: .inline, emphasis: .tertiary)
                    }
                }
                .padding(.vertical, 4)
            }
        }
    }

    private var reviewStep: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionIntro(
                String(localized: "Declaration & review"),
                String(localized: "Review your report before submitting. By continuing, you confirm accuracy.")
            )
            reviewRow(String(localized: "Full legal name"), draft.fullName)
            reviewRow(String(localized: "Email"), draft.email)
            reviewRow(String(localized: "Phone"), "\(draft.country.dial) \(draft.phoneLocal)")
            reviewRow(String(localized: "Country"), draft.country.name)
            reviewRow(String(localized: "Category"), CustomerCybercrimeCatalog.categoryTitle(draft.category))
            reviewRow(String(localized: "Urgency"), CustomerCybercrimeCatalog.urgencyTitle(draft.urgency))
            reviewRow(String(localized: "Platforms"), draft.platformsText)
            reviewRow(String(localized: "Description"), draft.description)
            reviewRow(String(localized: "Evidence"), "\(draft.allFiles().count) \(String(localized: "files"))")
            Toggle(String(localized: "I confirm that all information is true and accurate to the best of my knowledge."), isOn: $draft.declTruthful)
            Toggle(String(localized: "I understand that false or misleading reports may be rejected."), isOn: $draft.declFalseReports)
            Toggle(String(localized: "I agree that additional verification steps may be required before assistance is provided."), isOn: $draft.declVerification)
        }
        .font(PAXTypography.meta)
        .tint(PAXTheme.accent)
    }

    private func reviewRow(_ label: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label)
                .font(PAXTypography.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
            Text(value.isEmpty ? String(localized: "None") : value)
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textPrimary)
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 14, elevation: 0)
    }

    private var footer: some View {
        HStack(spacing: 12) {
            if draft.step != .identity {
                Button(String(localized: "Back")) {
                    withAnimation(PAXMotion.tabSelect) {
                        draft.step = CustomerCybercrimeDraft.Step(rawValue: draft.step.rawValue - 1) ?? .identity
                    }
                }
                .buttonStyle(PAXRevolutPressableStyle())
                .frame(maxWidth: 100, minHeight: 52)
            }
            if draft.step == .review {
                PAXRevolutPrimaryButton(
                    title: draft.isSubmitting ? String(localized: "Submitting…") : String(localized: "Submit report"),
                    isLoading: draft.isSubmitting
                ) {
                    Task { await submit() }
                }
                .disabled(!draft.canSubmit || draft.isSubmitting)
            } else {
                PAXRevolutPrimaryButton(title: String(localized: "Continue")) {
                    guard canAdvance else { return }
                    withAnimation(PAXMotion.tabSelect) {
                        draft.step = CustomerCybercrimeDraft.Step(rawValue: draft.step.rawValue + 1) ?? .review
                    }
                }
                .disabled(!canAdvance)
            }
        }
        .padding(.horizontal, PAXSpacing.screenHorizontal)
        .padding(.vertical, 12)
        .background(.ultraThinMaterial)
    }

    private var canAdvance: Bool {
        switch draft.step {
        case .identity: return draft.canContinueIdentity
        case .incident: return draft.canContinueIncident
        case .evidence: return true
        case .review: return draft.canSubmit
        }
    }

    private func sectionIntro(_ title: String, _ body: String) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title)
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textPrimary)
            Text(body)
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private func submit() async {
        guard draft.canSubmit else { return }
        draft.isSubmitting = true
        defer { draft.isSubmitting = false }
        do {
            let sessionID = try? await api.fetchChatSession().session_id
            let response = try await api.submitCybercrimeReport(
                fields: draft.fields(chatSessionID: sessionID),
                files: draft.allFiles()
            )
            PAXHaptics.success()
            onFinished()
            dismiss()
            let reference = response.reference
            if !reference.isEmpty {
                navigation.openCybercrime(reference: reference)
            }
        } catch let error as CustomerAPIError {
            if case .serverCode("active_report_exists", _) = error {
                onFinished()
                dismiss()
                navigation.openCybercrime()
                return
            }
            draft.error = error.localizedDescription
            PAXHaptics.warning()
        } catch {
            draft.error = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}

private struct FlexiblePlatformChips: View {
    @Binding var selection: Set<String>

    var body: some View {
        FlexibleWrap(spacing: 8) {
            ForEach(CustomerCybercrimeCatalog.platforms, id: \.self) { name in
                let selected = selection.contains(name)
                Button {
                    if selected { selection.remove(name) } else { selection.insert(name) }
                } label: {
                    Text(name)
                        .font(PAXTypography.caption.weight(.semibold))
                        .foregroundStyle(selected ? PAXTheme.onAccent : PAXTheme.textPrimary)
                        .padding(.horizontal, 12)
                        .padding(.vertical, 8)
                        .background(selected ? AnyShapeStyle(PAXBrandGradient.linear) : AnyShapeStyle(PAXTheme.surfaceElevated))
                        .clipShape(Capsule())
                }
                .buttonStyle(.plain)
            }
        }
    }
}

private struct FlexibleWrap<Content: View>: View {
    var spacing: CGFloat
    @ViewBuilder var content: Content

    var body: some View {
        _VariadicWrap(spacing: spacing) { content }
    }
}

private struct _VariadicWrap<Content: View>: View {
    var spacing: CGFloat
    @ViewBuilder var content: Content

    var body: some View {
        FlowWrap(spacing: spacing) { content }
    }
}

private struct FlowWrap: Layout {
    var spacing: CGFloat = 8

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        arrange(proposal: proposal, subviews: subviews).size
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let result = arrange(proposal: proposal, subviews: subviews)
        for (index, frame) in result.frames.enumerated() {
            subviews[index].place(at: CGPoint(x: bounds.minX + frame.minX, y: bounds.minY + frame.minY), proposal: .unspecified)
        }
    }

    private func arrange(proposal: ProposedViewSize, subviews: Subviews) -> (size: CGSize, frames: [CGRect]) {
        let maxWidth = proposal.width ?? .infinity
        var x: CGFloat = 0
        var y: CGFloat = 0
        var rowHeight: CGFloat = 0
        var frames: [CGRect] = []
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x + size.width > maxWidth, x > 0 {
                x = 0
                y += rowHeight + spacing
                rowHeight = 0
            }
            frames.append(CGRect(origin: CGPoint(x: x, y: y), size: size))
            rowHeight = max(rowHeight, size.height)
            x += size.width + spacing
        }
        return (CGSize(width: maxWidth, height: y + rowHeight), frames)
    }
}
