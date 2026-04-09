/**
 * Google Apps Script for "Opulent influensor house" Sheet
 * Receives form data from your website and appends a row.
 *
 * SETUP:
 * 1. Open your sheet: https://docs.google.com/spreadsheets/d/1kDLoGY230GAbmziSvIzjoxi3YAo-0MZ8BM70-sBYhTo/edit
 * 2. Extensions → Apps Script. Delete any sample code.
 * 3. Paste this entire file. Save (Ctrl/Cmd+S).
 * 4. Run doPost once (Run → doPost). When asked, authorize the app.
 * 5. Deploy → New deployment → Type: Web app.
 *    - Execute as: Me. Who has access: Anyone.
 * 6. Deploy. Copy the "Web app URL" and paste it in website.html (search for FORM_SUBMIT_URL).
 *
 * Column order (row 1 headers): Timestamp, Influencer ID, Full Name, Email, Phone, Country, City, Age, Gender,
 * Influencer Type, Experience (Years), Brand Collaborations, Past Brands, Platforms Active,
 * Instagram Username, Instagram Followers, Instagram Reach, Instagram Engagement,
 * TikTok Username, TikTok Followers, TikTok Views, YouTube Channel, YouTube Subscribers, YouTube Views,
 * Audience Countries, Audience Gender Split, Audience Age Groups, Primary Niche, Secondary Niche, Content Category Tags,
 * Collaboration Types, Preferred Platforms, Price Per Post, Currency, Negotiable, Availability Status,
 * Media Kit File Name, Analytics Screenshot File Name, Verified Status, Admin Approval Status, Influencer Tier, Internal Notes
 */

function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({ result: 'Use POST to submit form data.' }))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    var params = e.parameter;

    // If you sent JSON in the body instead of form params, uncomment below:
    // var params = e.postData && e.postData.contents ? JSON.parse(e.postData.contents) : e.parameter;

    var row = [
      new Date(),
      params.influencerId || '',
      params.fullName || '',
      params.email || '',
      params.phone || '',
      params.country || '',
      params.city || '',
      params.age || '',
      params.gender || '',
      params.influencerType || '',
      params.experienceYears || '',
      params.brandCollaborations || '',
      params.pastBrands || '',
      params.platformsActive || '',
      params.instagramUsername || '',
      params.instagramFollowers || '',
      params.instagramReach || '',
      params.instagramEngagement || '',
      params.tiktokUsername || '',
      params.tiktokFollowers || '',
      params.tiktokViews || '',
      params.youtubeChannel || '',
      params.youtubeSubscribers || '',
      params.youtubeViews || '',
      params.audienceCountries || '',
      params.audienceGenderSplit || '',
      params.audienceAgeGroups || '',
      params.primaryNiche || '',
      params.secondaryNiche || '',
      params.contentCategoryTags || '',
      params.collaborationTypes || '',
      params.preferredPlatforms || '',
      params.pricePerPost || '',
      params.currency || '',
      params.negotiable || '',
      params.availabilityStatus || '',
      params.mediaKitFileName || '',
      params.analyticsScreenshotFileName || '',
      params.verifiedStatus || '',
      params.adminApprovalStatus || '',
      params.influencerTier || '',
      params.internalNotes || ''
    ];

    sheet.appendRow(row);
    return ContentService.createTextOutput(JSON.stringify({ success: true, message: 'Data saved.' }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ success: false, error: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}