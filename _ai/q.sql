select id,name,status,property_type_id,location_id,avg_rating,reviews_count,favorites_count from properties;
select id,name,location_id,avg_rating,reviews_count,favorites_count from restaurants;
select count(*) as reviews, min(rating) as min_rating, max(rating) as max_rating from reviews;
select count(*) as favorites from favorites;
select id, slug, properties_count, restaurants_count from locations order by sort_order;
select count(*) as media from media;
