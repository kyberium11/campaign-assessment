# Campaign Assessment Logic

This document outlines the mathematical formulas and scoring rules used to calculate campaign health scores, based on the original Airtable automation script.

## 1. Evaluation Methodology
Each of the six metrics is evaluated differently, either as an absolute value for the current month or as a comparison against historical data.

| Metric | Evaluation Method | Comparison Target |
| :--- | :--- | :--- |
| **Website Speed** | Absolute Value | Current Month |
| **Leads** | % Growth/Decline | Same Month, **Previous Year** |
| **Rankings** | % Growth/Decline | **Previous Month** |
| **Traffic** | % Growth/Decline | Same Month, **Previous Year** |
| **Engagement** | Absolute Value | Current Month (Time in Seconds) |
| **Conversion** | % Growth/Decline | Same Month, **Previous Year** |

---

## 2. Metric Scoring Ranges (1-5 Scale)
The system applies specific ranges to determine a score for each category. These scores are the foundation of the Health Score.

### A. Website Speed (Absolute %)
- **Score 1**: ≤ 49%
- **Score 2**: 50% - 63%
- **Score 3**: 64% - 76%
- **Score 4**: 77% - 89%
- **Score 5**: ≥ 90%

### B. Leads, Traffic, & Conversion (% Comparison vs. Prev Year)
- **Score 1**: ≤ -50%
- **Score 2**: -49% to -16%
- **Score 3**: -15% to 0%
- **Score 4**: 1% to 10%
- **Score 5**: ≥ 11%

### C. Rankings (% Comparison vs. Prev Month)
- **Score 1**: ≤ -50%
- **Score 2**: -49% to -6%
- **Score 3**: -5% to 5%
- **Score 4**: 6% to 20%
- **Score 5**: ≥ 21%

### D. Engagement (Absolute Time)
- **Score 1**: ≤ 30s
- **Score 2**: 31s - 60s
- **Score 3**: 61s - 90s
- **Score 4**: 91s - 120s
- **Score 5**: ≥ 121s

---

## 3. Final Health Score & Assessment Label
The **Health Score** is the simple arithmetic average of the six category scores:

`Health Score = (Speed + Leads + Rankings + Traffic + Engagement + Conversion) / 6`

The final **Campaign Assessment** label is determined by this average:

| Health Score Range | Assessment Label | Status Class |
| :--- | :--- | :--- |
| **< 2.0** | Poor Performance | `status-poor` |
| **2.0 – 2.9** | Stable | `status-stable` |
| **3.0 – 3.7** | Meets Expectations | `status-meets` |
| **3.8 – 4.2** | Exceeds Expectations | `status-exceeds` |
| **> 4.2** | Excellent Performance | `status-excellent` |

---

## 4. Technical Implementation Notes
- **ID Formatting**: Metric IDs are generated using the pattern `CampaignCode-MonthName-Year` (e.g., `AtCA-February-2026`).
- **Data Lookup**: The script performs an optimized lookup by mapping all metrics into an associative array indexed by their `metrics_id`.
- **Missing Data (Fallback)**: If no historical comparison data exists (e.g., first month for a campaign), the system assigns a mid-range score of **3** to ensure the final average remains fair.
- **Data Cleanup**: CSV percentage strings (e.g., "55%") are automatically parsed into decimals (0.55) during import for mathematical processing.
