# Ileza Roadmap

Ileza helps people decide how much they would pay for a product through Bahdan Hal's personal price estimates, dated histories, and community feedback. The next stage of development will make these estimates easier to understand, open the catalog to more product categories, and give readers useful reasons to return.

This roadmap describes planned work. Delivery will follow the phases below; scope and sequencing may evolve based on user feedback and the quality of available data.

## Research and experimentation across all phases

A substantial part of the planned development is research and development (R&D): testing product ideas, building prototypes, comparing technical approaches, and learning from real usage. Experimentation will accompany every phase, including community feedback, category expansion, interface design, editorial tools, localization, and price analytics.

- Define a hypothesis and a measurable outcome for each major experiment.
- Explore alternative interaction designs through prototypes and usability testing before broader rollout.
- Evaluate AI-assisted workflows for product variant matching, research support, and editorial drafting, with human review before publication.
- Compare approaches to community feedback aggregation, data quality checks, and market-specific estimates.
- Use limited pilots and, where traffic permits, controlled comparisons to assess usefulness and maintenance effort.
- Document methods, findings, limitations, and unsuccessful approaches in the repository so experiments inform later work.
- Expand promising experiments into supported features; revise or stop approaches that do not demonstrate value.

**Expected result:** Development produces both useful product improvements and documented findings that guide future decisions. Experimental features remain clearly identified until their quality has been evaluated.

## Phase 1: Community feedback, clearer design, and new categories

### Feedback on price estimates

- Add three reactions to product pages: "Agree", "I would pay less", and "I would pay more".
- Allow readers to suggest an amount and briefly explain their reasoning.
- Display the author's estimate and community feedback separately, with participation counts and clear labels.
- Start with participation that does not require an account, supported by duplicate-vote and abuse controls.
- Make sparse feedback visible as such, so a small number of responses does not imply a reliable consensus.

**Expected result:** Readers can express their own view of a product's value and compare it with the author's estimate.

### Design and usability improvements

- Make the current estimate, observation date, and price history easy to find and understand.
- Refine product cards, category navigation, filters, and mobile layouts.
- Improve chart labels, empty states, accessibility, and consistency across pages.
- Support short editorial explanations of an estimate, including factors that affect value and relevant buying conditions.

**Expected result:** Readers can quickly understand the estimate, its freshness, and how it has changed.

### Popular cosmetics as a category pilot

- Introduce a curated selection of popular cosmetics to test demand beyond electronics.
- Add a dedicated category page and filters suited to the selected products.
- Distinguish exact variants, including volume, shade, and set contents where relevant.
- Use product requests and reader feedback to guide subsequent category expansion.

**Expected result:** A usable cosmetics collection demonstrates that the catalog can serve another audience while preserving precise product definitions.

### Phase completion criteria

- Readers can submit reactions and suggested amounts on product pages, and see community feedback separately from the author's estimate.
- Updated product pages clearly present the estimate, date, and history on mobile and desktop.
- The cosmetics pilot has published estimates, consistent variant definitions, and working category navigation.

## Phase 2: Editorial publishing and return visits

### A richer editorial CMS

- Improve the article editor with drafts, previews, and reusable content blocks.
- Support images, comparison tables, product advantages and limitations, and curated recommendations.
- Extend catalog-linked price widgets so articles can include current estimates without manually duplicating prices.
- Make it easier to publish buying guides, category explainers, and budget-based product selections.

**Expected result:** The author can publish polished, useful articles whose embedded estimates stay aligned with the catalog.

### Saved products and notifications

- Let readers save products they want to follow.
- Allow a target price for each followed product.
- Offer optional notifications when a published estimate reaches the target or receives a significant update.
- Provide clear controls for notification preferences and unsubscribing.
- Explain that notifications refer to published Ileza estimates; any future listing-based alerts will identify their source separately.

**Expected result:** Readers can follow relevant products and return when there is a meaningful update.

### Phase completion criteria

- The editor supports drafting, previewing, and publishing an article with reusable blocks and catalog-linked estimates.
- Updating a catalog estimate is reflected in articles that reference it.
- Readers can save products, configure a target, receive an eligible notification, and disable notifications.

## Phase 3: Additional markets and experimental price analytics

### A second market pilot

- Select one additional market based on demand and the availability of reliable local information.
- Support local currency, language, product availability, and market-specific sources.
- Maintain separate estimates and histories for each market rather than treating currency conversion as a local valuation.
- Make the selected market explicit throughout browsing and product history pages.

**Expected result:** Readers in the pilot market receive locally relevant estimates and clearly separated histories.

### Historical insights and forecasts

- Start with descriptive insights: changes over time, observed historical ranges, and downward or upward trends.
- Assess whether the depth, frequency, and consistency of recorded data support forecasting.
- Experiment with forecast ranges for products with sufficient history, clearly stating the horizon and uncertainty.
- Evaluate predictions against later observations and simple baseline methods before expanding the feature.
- Distinguish forecasts of Ileza's published estimates from any future forecasts based on independently observed market prices.

**Expected result:** Readers gain useful historical context, with experimental forecasts introduced only where their meaning and accuracy can be evaluated.

### Phase completion criteria

- A limited product collection is available in the second market with local estimates and separate histories.
- Historical insights are available for eligible products.
- A documented forecasting evaluation determines whether forecasts are useful enough to publish more broadly; a positive result is not assumed.

## Measuring progress

Establish baselines before each rollout and compare results after release. Track:

- **Participation:** Share of viewed products receiving feedback, unique contributors, and repeat participation.
- **Retention:** Returning readers and use of saved products and notifications.
- **Catalog quality:** Estimate freshness, category coverage, and completeness of product variant definitions.
- **Category demand:** Visits, product requests, and feedback for the cosmetics pilot and later categories.
- **Publishing:** Time needed to publish a guide and reader engagement with articles and linked products.
- **Market expansion:** Use of the second-market catalog and the effort required to maintain current local estimates.
- **Forecast quality:** Error against later observations, interval coverage, and performance relative to simple baselines.
- **R&D outcomes:** Documented experiments, findings, and decisions to adopt, revise, or stop an approach.

Phases 1 and 2 are the primary development scope. Phase 3 is a subsequent expansion and research stage, informed by adoption and data collected during earlier releases.
