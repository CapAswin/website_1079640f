# Opulent Influencer House – API

## Master data (GET) – plain JSON array

- `GET /api/categories` → `[{ "category_id", "category_name", "status" }, ...]`
- `GET /api/niches?category_id=` → `[{ "niche_id", "category_id", "niche_name", "status" }, ...]`
- `GET /api/countries` → `[{ "country_id", "country_name", "currency" }, ...]`
- `GET /api/provinces?country_id=` → `[{ "province_id", "country_id", "province_name" }, ...]`
- `GET /api/social-platforms` → `[{ "platform_id", "platform_name", "base_url", "status" }, ...]`

## Registration (4 steps, no token)

1. **POST /api/register/init**  
   Body: `user_type` (influencer/brand), `email`, `password`.  
   Returns: `user_id`, `user_type`, `requires_verification`.

2. **POST /api/register/influencer/profile**  
   Body: `influencer_id`, `full_name`, `country_id`, `province_id`, `city_name`, `date_of_birth`, `gender`, `influencer_type`.  
   Returns: `influencer_id`, `next_step: "/register/niche"`.

3. **POST /api/register/influencer/niche**  
   Body: `influencer_id`, `bio`, `experience_since`, `past_brands`, `primary_niche_id`, `secondary_niche_id`, `content_categories`.  
   Returns: `influencer_id`, `next_step: "/register/social-pricing"`.

4. **POST /api/register/influencer/social-pricing**  
   Content-Type: `multipart/form-data`.  
   Fields: `influencer_id`, `open_to_brand_collab`, `price_per_post`, `is_negotiable`, `collaboration_type`, `document_type`, `document_file` (file), `social_accounts` (JSON string).  
   Returns: `influencer_id`, `verification_status`, `redirect_url`.

## Setup

1. Edit `config/config.php` with your DB credentials (and optional `cors_origins`, `debug`).
2. Document root = `public/`.
