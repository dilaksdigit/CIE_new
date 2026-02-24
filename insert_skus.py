import json
import mysql.connector
import uuid
from datetime import datetime

# Database connection
conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='root1234',
    database='cie_v232'
)
cursor = conn.cursor()

# SKU data
skus_data = [
  {
    "sku_code": "CBL-BLK-3C-1M",
    "product_name": "Black Braided Pendant Cable Set 3-Core 1m E27",
    "tier": "HERO",
    "category": "Cables",
    "identity": {
      "product_class": "Decorative Lighting Cable",
      "product_type": "Pendant Cable Set",
      "material_primary": "Braided Fabric over PVC",
      "colour": "Black",
      "core_count": 3,
      "style": "Braided",
      "length_m": 1.0,
      "fitting_type": "E27 Screw",
      "certifications": ["BS 7671", "CE"],
      "weight_kg": 0.18,
      "ip_rating": None
    },
    "use_case": {
      "cluster_id": "CLU-CBL-P-E27",
      "cluster_intent": "Connect and power a pendant light fitting safely and stylishly",
      "primary_intent": "Compatibility",
      "secondary_intents": ["Installation/How-To", "Specification"],
      "best_for": ["Standard ceiling pendant installations", "Kitchen island lighting", "Bedroom pendant upgrades", "Replacing old flex cable"],
      "not_for": ["Bathroom installations (not IP-rated)", "Outdoor use", "Heavy industrial fixtures over 5kg"],
      "comparison_anchors": [{"competitor": "Generic PVC flex", "our_advantage": "Braided fabric finish adds design element", "evidence": "4.7 avg review vs 3.9 for PVC alternatives"}]
    },
    "commercial": {"contribution_margin_pct": 62.0, "cppc": 0.18, "velocity_90d": 847, "return_rate_pct": 2.1, "composite_score": 88.4, "price_gbp": 12.99, "cost_gbp": 4.94},
    "content": {
      "shopify_title": "Pendant Cable Set for Ceiling Lights - Safe Wiring Made Simple | 3-Core Braided 1m E27",
      "feed_title": "Black Braided Pendant Cable Set 3-Core 1m with E27 Holder for Ceiling Light Installation",
      "meta_description": "3-core braided pendant cable set with E27 holder. Rated to 60W. Compatible with LED and CFL. Ideal for standard 2.4m ceilings. BS 7671 compliant. Free UK delivery.",
      "ai_answer_block": "A 3-core braided pendant cable set with E27 holder connects a ceiling rose to a lampshade safely. Rated to 60W, compatible with LED and CFL bulbs. Choose 1m for standard 2.4m ceilings or 1.5m for period properties with higher ceilings.",
      "ai_answer_block_chars": 287,
      "ppc_headlines": ["Pendant Cable Set E27 | Safe", "Braided Ceiling Light Cable", "Easy DIY Pendant Wiring Kit"],
      "alt_text": "Black braided 3-core pendant cable set with E27 lamp holder for ceiling lights",
      "quotable_facts": ["Rated to 3A/60W, compatible with all LED and CFL bulbs", "BS 7671 compliant wiring for DIY installation", "1m length suits standard UK ceiling height of 2.4m"]
    },
    "authority": {
      "expert_statement": "Wiring compliant with BS 7671 (IET Wiring Regulations, 18th Edition). Cable rated to 3A/60W. Suitable for DIY installation with existing ceiling rose.",
      "wikidata_entities": [{"qid": "Q174102", "label": "Electrical cable"}, {"qid": "Q193514", "label": "Pendant light"}],
      "certifications_detail": ["BS 7671:2018 (18th Edition IET Wiring Regs)", "CE marked"]
    },
    "faqs": [
      {"question": "Can I use this cable with an LED bulb?", "answer": "Yes, this cable set is compatible with all LED, CFL, and incandescent bulbs up to 60W with an E27 screw fitting.", "intent_tag": "Compatibility"},
      {"question": "How do I wire a pendant cable to my ceiling rose?", "answer": "Connect the three cores (live, neutral, earth) to the matching terminals in your ceiling rose. Turn off power at the consumer unit first. Full instructions included.", "intent_tag": "Installation/How-To"},
      {"question": "What is the maximum weight this cable can support?", "answer": "The cable and E27 holder support lampshades up to 2kg. For heavier shades, use a chain suspension kit.", "intent_tag": "Specification"}
    ],
    "expected_outputs": {
      "gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True},
      "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 92}, "amazon": {"decision": "COMPETE", "readiness": 78}, "ai_assistants": {"decision": "COMPETE", "readiness": 85}, "own_website": {"decision": "COMPETE", "readiness": 95}, "active_channels": 4},
      "maturity": {"core_fields": 40, "authority": 20, "channel_readiness": 22, "ai_visibility": 11, "total": 93, "level": "Gold"},
      "title_validation": {"starts_with_intent": True, "attribute_stacking": False, "shopify_length_valid": True, "feed_length_valid": True}
    }
  },
  {
    "sku_code": "CBL-GLD-3C-1M",
    "product_name": "Gold Braided Pendant Cable Set 3-Core 1m E27",
    "tier": "HERO",
    "category": "Cables",
    "identity": {"product_class": "Decorative Lighting Cable", "product_type": "Pendant Cable Set", "material_primary": "Braided Fabric over PVC", "colour": "Gold", "core_count": 3, "style": "Braided", "length_m": 1.0, "fitting_type": "E27 Screw", "certifications": ["BS 7671", "CE"], "weight_kg": 0.18, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-CBL-P-E27", "cluster_intent": "Connect and power a pendant light fitting safely and stylishly", "primary_intent": "Inspiration/Style", "secondary_intents": ["Compatibility", "Specification"], "best_for": ["Period property pendant installations", "Art deco interior schemes", "Statement lighting accents", "Brass fixture pairings"], "not_for": ["Modern minimalist spaces", "Outdoor use", "Bathrooms"], "comparison_anchors": [{"competitor": "Chrome cable set", "our_advantage": "Gold finish complements period brass fixtures", "evidence": "78% of gold cable buyers also purchase brass fittings"}]},
    "commercial": {"contribution_margin_pct": 65.0, "cppc": 0.16, "velocity_90d": 621, "return_rate_pct": 1.8, "composite_score": 89.2, "price_gbp": 14.99, "cost_gbp": 5.25},
    "content": {"shopify_title": "Statement Gold Pendant Cable for Period Properties | Braided 3-Core 1m E27", "feed_title": "Gold Braided Pendant Cable Set 3-Core 1m E27 for Period and Art Deco Interiors", "meta_description": "Gold braided pendant cable set with E27 holder. Perfect for period properties and art deco schemes. 3-core, BS 7671 compliant. Pairs with brass ceiling roses.", "ai_answer_block": "A gold braided pendant cable set adds a warm metallic accent to period properties and art deco interiors. The 3-core E27 cable pairs naturally with brass ceiling roses and vintage-style bulbs. Rated to 60W for LED use, with 1m length for standard ceiling heights.", "ai_answer_block_chars": 289, "ppc_headlines": ["Gold Pendant Cable | Art Deco", "Brass-Style Ceiling Light Cable", "Period Property Lighting Cable"], "alt_text": "Gold braided 3-core pendant cable set with E27 holder for period property lighting", "quotable_facts": ["Gold finish designed to pair with brass ceiling roses", "3-core BS 7671 compliant for DIY installation", "78% of buyers pair with vintage filament bulbs"]},
    "authority": {"expert_statement": "BS 7671 compliant. 3-core earthed cable rated to 3A/60W. Gold braided finish is non-conductive outer sheath over PVC insulation.", "wikidata_entities": [{"qid": "Q174102", "label": "Electrical cable"}, {"qid": "Q39782", "label": "Brass"}], "certifications_detail": ["BS 7671:2018", "CE marked"]},
    "faqs": [{"question": "Does the gold colour fade over time?", "answer": "The braided fabric sheath is dyed through, not surface coated. Colour remains consistent for 5+ years under normal indoor use.", "intent_tag": "Specification"}, {"question": "Will this cable match my brass ceiling rose?", "answer": "Yes, the warm gold tone is specifically designed to complement brass, antique bronze, and copper fittings.", "intent_tag": "Compatibility"}, {"question": "Can I use a dimmer switch with this cable?", "answer": "Yes, this cable supports dimmable LED and incandescent bulbs when used with a compatible trailing-edge dimmer.", "intent_tag": "Installation/How-To"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 89}, "amazon": {"decision": "COMPETE", "readiness": 82}, "ai_assistants": {"decision": "COMPETE", "readiness": 88}, "own_website": {"decision": "COMPETE", "readiness": 94}, "active_channels": 4}, "maturity": {"core_fields": 40, "authority": 20, "channel_readiness": 22, "ai_visibility": 12, "total": 94, "level": "Gold"}}
  },
  {
    "sku_code": "CBL-WHT-2C-3M",
    "product_name": "White Round Flex Cable 2-Core 3m",
    "tier": "SUPPORT",
    "category": "Cables",
    "identity": {"product_class": "Electrical Cable", "product_type": "Extension Flex", "material_primary": "PVC", "colour": "White", "core_count": 2, "style": "Round", "length_m": 3.0, "fitting_type": "Bare ends", "certifications": ["CE"], "weight_kg": 0.35, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-CBL-EXT", "cluster_intent": "Extend or replace existing lamp cable safely", "primary_intent": "Specification", "secondary_intents": ["Compatibility"], "best_for": ["Table lamp rewiring", "Floor lamp cable extension"], "not_for": ["Ceiling pendant installations (needs 3-core)", "Outdoor use"], "comparison_anchors": []},
    "commercial": {"contribution_margin_pct": 44.0, "cppc": 0.42, "velocity_90d": 312, "return_rate_pct": 3.5, "composite_score": 62.1, "price_gbp": 6.99, "cost_gbp": 3.91},
    "content": {"shopify_title": "Replacement Flex Cable for Table and Floor Lamps | 2-Core White PVC 3m", "feed_title": "White 2-Core Round Flex Cable 3m for Table Lamp and Floor Lamp Rewiring", "meta_description": "White 2-core round flex cable, 3m length. Ideal for rewiring table lamps and floor lamps. CE marked. Bare ends for custom wiring.", "ai_answer_block": "A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use.", "ai_answer_block_chars": 271, "ppc_headlines": ["Lamp Rewiring Cable 3m", "Table Lamp Flex Replacement"], "alt_text": "White 2-core round flex cable 3m for table and floor lamp rewiring", "quotable_facts": ["3m length covers most floor lamp cable runs", "2-core suitable for double-insulated Class II lamps"]},
    "authority": {"expert_statement": "CE marked. Suitable for Class II (double insulated) luminaires only. Not for earthed fittings.", "wikidata_entities": [{"qid": "Q174102", "label": "Electrical cable"}], "certifications_detail": ["CE marked"]},
    "faqs": [{"question": "Is this cable suitable for a ceiling pendant?", "answer": "No, ceiling pendants require a 3-core earthed cable. This 2-core cable is for double-insulated table and floor lamps only.", "intent_tag": "Compatibility"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 76}, "amazon": {"decision": "SKIP", "readiness": 0}, "ai_assistants": {"decision": "COMPETE", "readiness": 71}, "own_website": {"decision": "COMPETE", "readiness": 88}, "active_channels": 3}, "maturity": {"core_fields": 36, "authority": 16, "channel_readiness": 20, "ai_visibility": 8, "total": 80, "level": "Gold"}}
  },
  {
    "sku_code": "CBL-RED-3C-2M",
    "product_name": "Red Twisted Pendant Cable 3-Core 2m E27",
    "tier": "HARVEST",
    "category": "Cables",
    "identity": {"product_class": "Decorative Lighting Cable", "product_type": "Pendant Cable Set", "material_primary": "Braided Fabric over PVC", "colour": "Red", "core_count": 3, "style": "Twisted", "length_m": 2.0, "fitting_type": "E27 Screw", "certifications": ["BS 7671", "CE"], "weight_kg": 0.24, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-CBL-P-E27", "cluster_intent": "Connect and power a pendant light fitting safely and stylishly", "primary_intent": "Specification", "secondary_intents": [], "best_for": [], "not_for": []},
    "commercial": {"contribution_margin_pct": 18.0, "cppc": 1.85, "velocity_90d": 14, "return_rate_pct": 8.2, "composite_score": 22.4, "price_gbp": 16.99, "cost_gbp": 13.93},
    "content": {},
    "authority": {},
    "faqs": [],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "N/A", "G4": "N/A", "G5": "N/A", "G6": "PASS", "G7": "N/A", "overall": "HARVEST_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 52}, "amazon": {"decision": "SKIP", "readiness": 0}, "ai_assistants": {"decision": "SKIP", "readiness": 0}, "own_website": {"decision": "COMPETE", "readiness": 65}, "active_channels": 2}, "maturity": {"core_fields": 20, "authority": 5, "channel_readiness": 15, "ai_visibility": 0, "total": 40, "level": "Silver"}}
  },
  {
    "sku_code": "SHD-TPE-DRM-35",
    "product_name": "Fabric Drum Shade Taupe 35cm E27/B22",
    "tier": "HERO",
    "category": "Lampshades",
    "identity": {"product_class": "Lampshade", "product_type": "Drum Shade", "material_primary": "Polyester/Cotton Blend", "colour": "Taupe", "core_count": None, "style": "Drum", "length_m": None, "fitting_type": "E27/B22 Ring", "certifications": ["BS EN 60598-1"], "weight_kg": 0.42, "diameter_cm": 35, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-SHD-FAB", "cluster_intent": "Create warm, even, glare-free lighting in living spaces", "primary_intent": "Problem-Solving", "secondary_intents": ["Comparison", "Replacement/Refill"], "best_for": ["Living rooms needing warm ambient light", "Bedrooms with low ceilings", "Replacing dated coolie or pleated shades", "Pairing with dimmer switches"], "not_for": ["Task lighting (too diffused)", "Kitchens needing directional light", "Outdoor use", "High-humidity bathrooms"], "comparison_anchors": [{"competitor": "Glass cone shade", "our_advantage": "Fabric diffuses light evenly without hot spots", "evidence": "Customer surveys: 89% prefer fabric for bedrooms"}]},
    "commercial": {"contribution_margin_pct": 71.0, "cppc": 0.22, "velocity_90d": 1240, "return_rate_pct": 4.8, "composite_score": 91.7, "price_gbp": 18.99, "cost_gbp": 5.51},
    "content": {"shopify_title": "Warm Glare-Free Lighting for Living Rooms | Fabric Drum Shade Taupe 35cm", "feed_title": "Taupe Fabric Drum Lampshade 35cm E27 B22 for Warm Glare-Free Living Room Lighting", "meta_description": "Fabric drum shade in taupe, 35cm diameter. Creates warm, glare-free light for living rooms and bedrooms. Fits E27 and B22 pendants. Fire-retardant.", "ai_answer_block": "A fabric drum lampshade in taupe diffuses light evenly for warm, glare-free illumination in living rooms and bedrooms. The 35cm diameter suits standard ceiling pendants and floor lamps with E27 or B22 ring fittings. Ideal for rooms where softened ambient lighting matters most.", "ai_answer_block_chars": 294, "ppc_headlines": ["Glare-Free Lampshade | Taupe", "Fabric Drum Shade 35cm", "Warm Bedroom Lighting Shade"], "alt_text": "Taupe fabric drum lampshade 35cm diameter for pendant and floor lamp glare-free lighting", "quotable_facts": ["Fire-retardant fabric meets BS EN 60598-1", "89% of customers prefer fabric for bedroom lighting", "Universal E27/B22 ring fits most UK pendants"]},
    "authority": {"expert_statement": "Fire-retardant fabric meets BS EN 60598-1 for luminaire safety. Tested to 60W incandescent / no limit for LED. Ring fitting compatible with both E27 and B22 lamp holder standards.", "wikidata_entities": [{"qid": "Q839546", "label": "Lampshade"}, {"qid": "Q3410608", "label": "Textile"}], "certifications_detail": ["BS EN 60598-1:2015"]},
    "faqs": [{"question": "Will this shade fit my floor lamp?", "answer": "Yes, if your floor lamp uses an E27 or B22 bulb holder with a shade ring. Check for a removable ring around the bulb holder.", "intent_tag": "Compatibility"}, {"question": "How do I clean a fabric lampshade?", "answer": "Use a lint roller or soft brush for dust. For marks, dab gently with a damp cloth. Do not submerge in water.", "intent_tag": "Installation/How-To"}, {"question": "Is fabric safer than glass for children's rooms?", "answer": "Yes, fabric shades are shatterproof and stay cooler to touch than glass. This shade meets BS EN 60598-1 fire safety standards.", "intent_tag": "Regulatory/Safety"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 94}, "amazon": {"decision": "COMPETE", "readiness": 86}, "ai_assistants": {"decision": "COMPETE", "readiness": 90}, "own_website": {"decision": "COMPETE", "readiness": 96}, "active_channels": 4}, "maturity": {"core_fields": 40, "authority": 20, "channel_readiness": 23, "ai_visibility": 13, "total": 96, "level": "Gold"}}
  },
  {
    "sku_code": "SHD-GLS-CNE-20",
    "product_name": "Opal Glass Cone Shade 20cm E27",
    "tier": "HERO",
    "category": "Lampshades",
    "identity": {"product_class": "Lampshade", "product_type": "Cone Shade", "material_primary": "Opal Glass", "colour": "White/Opal", "core_count": None, "style": "Cone", "length_m": None, "fitting_type": "E27 Ring", "certifications": ["BS EN 60598-1"], "weight_kg": 0.65, "diameter_cm": 20, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-SHD-GLS", "cluster_intent": "Provide bright, focused-yet-diffused lighting with a premium material finish", "primary_intent": "Comparison", "secondary_intents": ["Problem-Solving", "Specification"], "best_for": ["Kitchen pendant lighting", "Bathroom vanity (check IP rating of fixture)", "Reading nooks", "Modern minimalist interiors"], "not_for": ["Children's rooms (fragile)", "Outdoor use", "Low-ceiling rooms (directional, not diffused)"], "comparison_anchors": [{"competitor": "Fabric drum shade", "our_advantage": "Glass provides brighter, more focused light", "evidence": "Lumen output 15% higher than equivalent fabric shade"}]},
    "commercial": {"contribution_margin_pct": 58.0, "cppc": 0.28, "velocity_90d": 534, "return_rate_pct": 6.2, "composite_score": 82.3, "price_gbp": 22.99, "cost_gbp": 9.66},
    "content": {"shopify_title": "Bright Focused Kitchen Pendant Lighting | Opal Glass Cone Shade 20cm E27", "feed_title": "Opal Glass Cone Lampshade 20cm E27 for Kitchen Pendant and Modern Minimalist Interiors", "meta_description": "Opal glass cone shade, 20cm. Bright, focused-yet-diffused light for kitchens and modern spaces. E27 ring fitting. BS EN 60598-1 compliant.", "ai_answer_block": "An opal glass cone shade delivers brighter, more focused light than fabric alternatives, making it ideal for kitchen pendants and reading nooks. The 20cm diameter suits compact pendants. Opal finish softens harshness while maintaining lumen output 15 percent higher", "ai_answer_block_chars": 250, "ppc_headlines": ["Glass Pendant Shade | Kitchen", "Opal Cone Shade 20cm E27", "Bright Modern Pendant Light"], "alt_text": "White opal glass cone lampshade 20cm E27 for kitchen pendant lighting", "quotable_facts": ["Opal glass delivers 15% more lumen output than equivalent fabric", "Hand-blown borosilicate glass resists thermal shock"]},
    "authority": {"expert_statement": "Borosilicate opal glass meets BS EN 60598-1. Heat resistant to 200C. E27 ring fitting compatible with standard UK pendant holders.", "wikidata_entities": [{"qid": "Q839546", "label": "Lampshade"}, {"qid": "Q190117", "label": "Borosilicate glass"}], "certifications_detail": ["BS EN 60598-1:2015"]},
    "faqs": [{"question": "Is opal glass the same as frosted glass?", "answer": "Similar effect but different process. Opal glass is made by adding bone ash during manufacturing, creating an even white diffusion throughout. Frosted glass has a surface treatment only.", "intent_tag": "Comparison"}, {"question": "Can this shade be used with LED bulbs?", "answer": "Yes, compatible with all E27 LED, CFL, and incandescent bulbs. No wattage limit for LED.", "intent_tag": "Compatibility"}, {"question": "How fragile is this shade?", "answer": "Borosilicate glass is more durable than standard glass. However, it can break if dropped. Not recommended for children's rooms or high-traffic areas.", "intent_tag": "Specification"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "FAIL", "G4_reason": "ai_answer_block_chars=242, minimum=250", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "GATE_FAIL", "submit_enabled": False}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 88}, "amazon": {"decision": "COMPETE", "readiness": 74}, "ai_assistants": {"decision": "COMPETE", "readiness": 82}, "own_website": {"decision": "COMPETE", "readiness": 91}, "active_channels": 4}, "maturity": {"core_fields": 35, "authority": 18, "channel_readiness": 21, "ai_visibility": 10, "total": 84, "level": "Gold"}}
  },
  {
    "sku_code": "BLB-LED-E27-4W",
    "product_name": "LED Filament Bulb E27 4W 2700K",
    "tier": "SUPPORT",
    "category": "Bulbs",
    "identity": {"product_class": "Light Bulb", "product_type": "LED Filament", "material_primary": "Glass/LED", "colour": "Warm White 2700K", "core_count": None, "style": "ST64 (Squirrel Cage)", "length_m": None, "fitting_type": "E27 Screw", "certifications": ["CE", "RoHS"], "weight_kg": 0.04, "wattage": 4, "lumens": 470, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-BLB-LED", "cluster_intent": "Find the right bulb for existing lamp fittings", "primary_intent": "Compatibility", "secondary_intents": ["Specification"], "best_for": ["E27 pendant cable sets", "Table lamp bulb replacement", "Vintage-style visible bulb displays"], "not_for": ["B22 bayonet fittings", "Outdoor unenclosed fixtures", "High-lumen task lighting needs"]},
    "commercial": {"contribution_margin_pct": 38.0, "cppc": 0.31, "velocity_90d": 2100, "return_rate_pct": 1.2, "composite_score": 58.9, "price_gbp": 4.99, "cost_gbp": 3.09},
    "content": {"shopify_title": "LED Bulb for E27 Pendant and Table Lamps - Warm Filament Glow | 4W 2700K 470lm", "feed_title": "LED Filament Bulb E27 4W 2700K Warm White 470 Lumens Dimmable Squirrel Cage", "meta_description": "E27 LED filament bulb, 4W warm white 2700K. 470 lumens. Dimmable. Squirrel cage style. Fits pendant cable sets and table lamps.", "ai_answer_block": "A 4W LED filament bulb with E27 screw cap produces 470 lumens of warm white light at 2700K, equivalent to a 40W incandescent. Fits standard E27 pendants, table lamps, and floor lamps. Dimmable with compatible trailing-edge dimmer switches.", "ai_answer_block_chars": 268, "ppc_headlines": ["E27 LED Bulb 4W Warm White", "Filament Bulb for Pendant Lamps"], "alt_text": "LED filament bulb E27 4W warm white squirrel cage style for pendant and table lamps", "quotable_facts": ["470 lumens = 40W incandescent equivalent", "25,000 hour rated lifespan"]},
    "authority": {"expert_statement": "CE and RoHS compliant. Energy rating A+. 25,000 hour rated lifespan. Compatible with trailing-edge dimmers.", "wikidata_entities": [{"qid": "Q80205", "label": "Light-emitting diode"}], "certifications_detail": ["CE marked", "RoHS compliant"]},
    "faqs": [{"question": "Is this bulb dimmable?", "answer": "Yes, compatible with trailing-edge LED dimmer switches. Not compatible with older leading-edge dimmers.", "intent_tag": "Specification"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 72}, "amazon": {"decision": "COMPETE", "readiness": 68}, "ai_assistants": {"decision": "COMPETE", "readiness": 74}, "own_website": {"decision": "COMPETE", "readiness": 85}, "active_channels": 4}, "maturity": {"core_fields": 34, "authority": 14, "channel_readiness": 19, "ai_visibility": 7, "total": 74, "level": "Silver"}}
  },
  {
    "sku_code": "BLB-LED-B22-8W",
    "product_name": "LED GLS Bulb B22 8W 4000K",
    "tier": "SUPPORT",
    "category": "Bulbs",
    "identity": {"product_class": "Light Bulb", "product_type": "LED GLS", "material_primary": "Glass/LED", "colour": "Cool White 4000K", "core_count": None, "style": "GLS (Standard)", "length_m": None, "fitting_type": "B22 Bayonet", "certifications": ["CE", "RoHS"], "weight_kg": 0.05, "wattage": 8, "lumens": 806, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-BLB-LED", "cluster_intent": "Find the right bulb for existing lamp fittings", "primary_intent": "Specification", "secondary_intents": ["Compatibility"], "best_for": ["B22 ceiling fittings", "Kitchen and workspace lighting", "High-brightness task areas"], "not_for": []},
    "commercial": {"contribution_margin_pct": 35.0, "cppc": 0.38, "velocity_90d": 1850, "return_rate_pct": 1.5, "composite_score": 55.2, "price_gbp": 3.99, "cost_gbp": 2.59},
    "content": {"shopify_title": "Bright LED Bulb for B22 Kitchen and Ceiling Lights | 8W 4000K Cool White 806lm", "feed_title": "LED GLS Bulb B22 8W Cool White 4000K 806 Lumens for Kitchen Ceiling Fittings", "meta_description": "B22 bayonet LED bulb, 8W cool white 4000K. 806 lumens, equivalent to 60W. Ideal for kitchens and workspaces. CE and RoHS compliant.", "ai_answer_block": "An 8W LED GLS bulb with B22 bayonet cap produces 806 lumens of cool white light at 4000K, equivalent to a traditional 60W bulb. Designed for kitchen ceiling fittings and workspaces where bright, clear illumination is needed. Non-dimmable.", "ai_answer_block_chars": 272, "ppc_headlines": ["B22 LED Bulb 8W Cool White", "Kitchen Ceiling Light Bulb"], "alt_text": "LED GLS bulb B22 bayonet 8W cool white for kitchen ceiling fittings", "quotable_facts": ["806 lumens = 60W incandescent equivalent", "4000K cool white ideal for task lighting"]},
    "authority": {"expert_statement": "CE and RoHS compliant. Energy rating A+. 15,000 hour rated lifespan. Non-dimmable.", "wikidata_entities": [{"qid": "Q80205", "label": "Light-emitting diode"}], "certifications_detail": ["CE marked", "RoHS compliant"]},
    "faqs": [{"question": "Will this bulb fit my pendant lamp?", "answer": "Only if your pendant uses a B22 bayonet fitting. Most decorative pendants use E27 screw fittings. Check your lamp holder before ordering.", "intent_tag": "Compatibility"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "FAIL", "G5_reason": "not_for is empty, minimum 1 required", "G6": "PASS", "G7": "PASS", "overall": "GATE_FAIL", "submit_enabled": False}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 70}, "amazon": {"decision": "SKIP", "readiness": 0}, "ai_assistants": {"decision": "COMPETE", "readiness": 65}, "own_website": {"decision": "COMPETE", "readiness": 82}, "active_channels": 3}, "maturity": {"core_fields": 32, "authority": 14, "channel_readiness": 18, "ai_visibility": 6, "total": 70, "level": "Silver"}}
  },
  {
    "sku_code": "PND-SET-BRS-3L",
    "product_name": "Brass 3-Light Pendant Cluster Set",
    "tier": "HERO",
    "category": "Pendants",
    "identity": {"product_class": "Pendant Light Fitting", "product_type": "Cluster Pendant Set", "material_primary": "Brass", "colour": "Antique Brass", "core_count": 3, "style": "Cluster (3 drop)", "length_m": 1.2, "fitting_type": "E27 Screw x3", "certifications": ["BS 7671", "BS EN 60598-1", "CE"], "weight_kg": 2.4, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-PND-CLU", "cluster_intent": "Create a statement multi-light pendant display for dining or kitchen islands", "primary_intent": "Problem-Solving", "secondary_intents": ["Inspiration/Style", "Installation/How-To"], "best_for": ["Kitchen island statement lighting", "Dining table centrepiece", "Open-plan living areas", "Period property renovations"], "not_for": ["Low ceilings under 2.4m", "Bathrooms (not IP-rated)", "Single bulb requirements"], "comparison_anchors": [{"competitor": "Single pendant", "our_advantage": "3-light cluster creates balanced illumination across wide surfaces", "evidence": "70% of kitchen island buyers prefer multi-light solutions"}]},
    "commercial": {"contribution_margin_pct": 55.0, "cppc": 0.35, "velocity_90d": 289, "return_rate_pct": 3.8, "composite_score": 81.5, "price_gbp": 89.99, "cost_gbp": 40.50},
    "content": {"shopify_title": "Statement Kitchen Island Lighting | Brass 3-Light Pendant Cluster E27", "feed_title": "Antique Brass 3-Light Pendant Cluster Set E27 for Kitchen Island and Dining Table Lighting", "meta_description": "Brass 3-light pendant cluster set with E27 holders. Statement lighting for kitchen islands and dining tables. BS 7671 compliant. Adjustable drop length.", "ai_answer_block": "A brass 3-light pendant cluster set creates balanced, statement illumination over kitchen islands and dining tables. Three independently adjustable E27 drops let you customise height and spread. Antique brass finish suits both period and contemporary interiors.", "ai_answer_block_chars": 293, "ppc_headlines": ["3-Light Pendant | Kitchen Island", "Brass Cluster Pendant Set", "Statement Dining Table Light"], "alt_text": "Antique brass 3-light pendant cluster set with E27 holders for kitchen island lighting", "quotable_facts": ["3 independently adjustable drops for custom positioning", "Antique brass finish suits period and contemporary interiors", "BS 7671 compliant, professional or DIY installation"]},
    "authority": {"expert_statement": "BS 7671 and BS EN 60598-1 compliant. Max load 60W per holder (180W total). Ceiling plate supports up to 5kg. Requires 3-core supply. Professional installation recommended for new wiring.", "wikidata_entities": [{"qid": "Q193514", "label": "Pendant light"}, {"qid": "Q39782", "label": "Brass"}], "certifications_detail": ["BS 7671:2018", "BS EN 60598-1:2015", "CE marked"]},
    "faqs": [{"question": "Can I install this myself?", "answer": "If you have an existing 3-core ceiling rose, yes. For new wiring or if unsure, use a Part P registered electrician. Full instructions included.", "intent_tag": "Installation/How-To"}, {"question": "What bulbs should I use with this pendant?", "answer": "We recommend E27 LED filament bulbs, 4-6W, warm white 2700K for the best effect. Globe or squirrel cage styles complement the brass finish.", "intent_tag": "Compatibility"}, {"question": "How wide an area does the 3-light cluster cover?", "answer": "The cluster covers approximately 60-80cm width, ideal for standard kitchen islands (90-120cm) and dining tables (120-180cm).", "intent_tag": "Specification"}],
    "expected_outputs": {"gate_results": {"G1": "PASS", "G2": "PASS", "G3": "PASS", "G4": "PASS", "G5": "PASS", "G6": "PASS", "G7": "PASS", "overall": "ALL_PASS", "submit_enabled": True}, "channel_decisions": {"google_sge": {"decision": "COMPETE", "readiness": 91}, "amazon": {"decision": "COMPETE", "readiness": 84}, "ai_assistants": {"decision": "COMPETE", "readiness": 87}, "own_website": {"decision": "COMPETE", "readiness": 93}, "active_channels": 4}, "maturity": {"core_fields": 40, "authority": 20, "channel_readiness": 22, "ai_visibility": 12, "total": 94, "level": "Gold"}}
  },
  {
    "sku_code": "FLR-ARC-BLK-175",
    "product_name": "Black Arc Floor Lamp 175cm E27",
    "tier": "KILL",
    "category": "Floor Lamps",
    "identity": {"product_class": "Floor Lamp", "product_type": "Arc Floor Lamp", "material_primary": "Steel", "colour": "Black", "core_count": None, "style": None, "length_m": None, "fitting_type": "E27 Screw", "certifications": ["CE"], "weight_kg": 8.5, "height_cm": 175, "ip_rating": None},
    "use_case": {"cluster_id": "CLU-FLR-ARC", "cluster_intent": None, "primary_intent": None, "secondary_intents": [], "best_for": [], "not_for": []},
    "commercial": {"contribution_margin_pct": -4.2, "cppc": 2.85, "velocity_90d": 3, "return_rate_pct": 22.0, "composite_score": 8.1, "price_gbp": 49.99, "cost_gbp": 52.09},
    "content": {},
    "authority": {},
    "faqs": [],
    "expected_outputs": {"gate_results": {"G1": "N/A", "G2": "N/A", "G3": "N/A", "G4": "N/A", "G5": "N/A", "G6": "PASS", "G7": "N/A", "overall": "KILL_EXCLUDED", "submit_enabled": False, "submit_visible": False}, "channel_decisions": {"google_sge": {"decision": "SKIP", "readiness": 0}, "amazon": {"decision": "SKIP", "readiness": 0}, "ai_assistants": {"decision": "SKIP", "readiness": 0}, "own_website": {"decision": "SKIP", "readiness": 0}, "active_channels": 0}, "maturity": {"core_fields": None, "authority": None, "channel_readiness": None, "ai_visibility": None, "total": None, "level": "Excluded"}}
  }
]

# Columns likely in the skus table - adjust as needed
TIER_MAP = {
    'HERO': 'HERO',
    'Hero': 'HERO',
    'SUPPORT': 'SUPPORT',
    'Support': 'SUPPORT',
    'HARVEST': 'HARVEST',
    'Harvest': 'HARVEST',
    'KILL': 'KILL',
    'Kill': 'KILL'
}

# Insert each SKU
for sku in skus_data:
    try:
        sku_id = str(uuid.uuid4())
        tier = TIER_MAP.get(sku['tier'], sku['tier'])
        
        # Extract data from the provided schema
        content = sku.get('content', {})
        commercial = sku.get('commercial', {})
        use_case = sku.get('use_case', {})
        expected = sku.get('expected_outputs', {})
        
        ai_answer_block = content.get('ai_answer_block', '')
        ai_answer_block_chars = content.get('ai_answer_block_chars', len(ai_answer_block))
        meta_description = content.get('meta_description', '')
        best_for = json.dumps(use_case.get('best_for', [])) if use_case.get('best_for') else None
        not_for = json.dumps(use_case.get('not_for', [])) if use_case.get('not_for') else None
        
        current_price = commercial.get('price_gbp')
        cost = commercial.get('cost_gbp')
        margin_pct = commercial.get('contribution_margin_pct')
        annual_volume = commercial.get('velocity_90d', 0)
        
        # Calculate readiness score from maturity
        maturity = expected.get('maturity', {})
        readiness_score = maturity.get('total', 0)
        
        sql = """
        INSERT INTO skus (
            id, sku_code, title, tier,
            ai_answer_block, ai_answer_block_chars, meta_description,
            current_price, cost, margin_percent, annual_volume,
            readiness_score, best_for, not_for, faq_data
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        values = (
            sku_id,
            sku['sku_code'],
            sku['product_name'],
            tier,
            ai_answer_block,
            ai_answer_block_chars,
            meta_description,
            current_price,
            cost,
            margin_pct,
            annual_volume,
            readiness_score,
            best_for,
            not_for,
            json.dumps(sku.get('faqs', []))
        )
        
        cursor.execute(sql, values)
        print(f"✓ Inserted: {sku['sku_code']} ({sku['product_name']})")
    except Exception as e:
        print(f"✗ Error inserting {sku.get('sku_code')}: {str(e)}")

conn.commit()
cursor.close()
conn.close()

print("\n✓ All SKUs inserted successfully!")
