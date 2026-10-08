// ✅ ปรับปรุงฟังก์ชันสร้างการหมุน
function generateHorizontalBaseRotations(item, conditions = {}, itemId = null) {
    // ลบเงื่อนไขตรวจสอบสินค้าห้ามตะแคง
    // ถ้าเงื่อนไขไม่อนุญาตให้หมุน
    if (!conditions.allowRotation) {
        // คืนค่าเฉพาะการวางแนวปกติ
        return [{ 
            baseLength: item.length, 
            baseWidth: item.width,
            rotation: 'original' 
        }];
    }
    
    // ✅ สร้างการหมุนเฉพาะฐาน (ความกว้างและยาวเท่านั้น)
    const baseRotations = [
        { baseLength: item.length, baseWidth: item.width, rotation: 'original' },
        { baseLength: item.width, baseWidth: item.length, rotation: 'rotate_xy' }
    ];
    
    return baseRotations;
}


// ✅ ฟังก์ชันเรียงสินค้าตามพื้นที่ฐาน (เฉพาะความกว้างและยาว)
function sortItemsByBaseArea(items, conditions) {
    const sortedItems = [...items];
    
    switch (conditions.sortingOrder) {
        case 'area':
            // ✅ เรียงตามพื้นที่ฐาน (ยาว x กว้าง) จากมากไปน้อย
            sortedItems.sort((a, b) => (b.length * b.width) - (a.length * a.width));
            break;
        case 'weight':
            sortedItems.sort((a, b) => b.weight - a.weight);
            break;
        case 'length':
            // ✅ เรียงตามความยาวจากมากไปน้อย
            sortedItems.sort((a, b) => b.length - a.length);
            break;
        case 'width':
            // ✅ เรียงตามความกว้างจากมากไปน้อย
            sortedItems.sort((a, b) => b.width - a.width);
            break;
        case 'maxside':
            // ✅ เรียงตามด้านที่ยาวที่สุดของฐาน
            sortedItems.sort((a, b) => Math.max(b.length, b.width) - Math.max(a.length, a.width));
            break;
        case 'perimeter':
            // ✅ เรียงตามเส้นรอบรูปฐาน
            sortedItems.sort((a, b) => (2*(b.length + b.width)) - (2*(a.length + a.width)));
            break;
        default:
            // ✅ ค่า default: เรียงตามพื้นที่ฐานจากมากไปน้อย
            sortedItems.sort((a, b) => (b.length * b.width) - (a.length * a.width));
    }
    
    console.log('📊 การจัดเรียงสินค้า (เฉพาะฐาน):', {
        วิธี: conditions.sortingOrder,
        สินค้าแรก: sortedItems[0] ? `${sortedItems[0].name} (ฐาน: ${sortedItems[0].length}x${sortedItems[0].width}cm)` : 'ไม่มี',
        สินค้าสุดท้าย: sortedItems[sortedItems.length-1] ? `${sortedItems[sortedItems.length-1].name} (ฐาน: ${sortedItems[sortedItems.length-1].length}x${sortedItems[sortedItems.length-1].width}cm)` : 'ไม่มี'
    });
    
    return sortedItems;
}

// ✅ ฟังก์ชันสร้างการหมุนเฉพาะฐาน (ความกว้างและยาวเท่านั้น)
function generateHorizontalBaseRotations(item, conditions = {}, itemId = null) {
    // ลบเงื่อนไขตรวจสอบสินค้าห้ามตะแคง
    // ถ้าเงื่อนไขไม่อนุญาตให้หมุน
    if (!conditions.allowRotation) {
        // คืนค่าเฉพาะการวางแนวปกติ
        return [{ 
            baseLength: item.length, 
            baseWidth: item.width,
            rotation: 'original' 
        }];
    }
    
    // ✅ สร้างการหมุนเฉพาะฐาน (ความกว้างและยาวเท่านั้น)
    const baseRotations = [
        { baseLength: item.length, baseWidth: item.width, rotation: 'original' },
        { baseLength: item.width, baseWidth: item.length, rotation: 'rotate_xy' }
    ];
    
    return baseRotations;
}


// ✅ ฟังก์ชันคำนวณประสิทธิภาพแบบฐานเท่านั้น
function calculateBaseEfficiency(plan, containerData) {
    if (plan.length === 0) {
        return { area: 0, weight: 0, overall: 0 };
    }

    // ✅ คำนวณเฉพาะพื้นที่ฐาน
    const totalBaseArea = plan.reduce((sum, item) => {
        if (item.actualSize) {
            return sum + (item.actualSize.length * item.actualSize.width);
        }
        return sum + (item.length * item.width);
    }, 0);

    const totalWeight = plan.reduce((sum, item) => sum + item.weight, 0);
    
    const containerBaseArea = containerData.length * containerData.width;
    
    const areaEfficiency = (totalBaseArea / containerBaseArea) * 100;
    const weightEfficiency = (totalWeight / containerData.maxWeight) * 100;
    
    return {
        area: Math.round(areaEfficiency),
        weight: Math.round(weightEfficiency),
        overall: Math.round((areaEfficiency * 0.7 + weightEfficiency * 0.3))
    };
}

// ✅ ฟังก์ชันสร้าง heightMap 3D
function initialize3DHeightMap(container) {
    console.log(`📐 สร้าง 3D heightMap: ${container.length}x${container.width}`);
    
    const heightMap = [];
    for (let x = 0; x < container.length; x++) {
        heightMap[x] = [];
        for (let y = 0; y < container.width; y++) {
            heightMap[x][y] = 0; // ความสูงเริ่มต้น
        }
    }
    return heightMap;
}

// ✅ หาตำแหน่งในแถวปัจจุบัน
function findPositionInCurrentRow(item, rotation, heightMap, container, row, column, height, spacing, conditions) {
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // ✅ คำนวณตำแหน่งในแถว
    let x = column;
    let y = row * (itemWidth + spacing);
    
    // ✅ ตรวจสอบว่าพอดีกับแถวหรือไม่
    if (y + itemWidth > container.width) {
        return null; // ไม่พอดีกับความกว้าง
    }
    
    // ✅ ตรวจสอบความยาวตู้
    if (x + itemLength > container.length) {
        return null; // ไม่พอดีในความยาว
    }
    
    // ✅ ตรวจสอบพื้นที่ว่าง
    if (!isAreaAvailable(heightMap, x, y, rotation, height, spacing, container)) {
        return null;
    }
    
    return {
        x: x,
        y: y,
        z: height,
        row: row,
        column: x + itemLength + spacing,
        height: height
    };
}

// ✅ หาตำแหน่งในแถวใหม่
function findPositionInNewRow(item, rotation, heightMap, container, currentRow, currentHeight, spacing, conditions) {
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // ✅ ลองแถวใหม่
    for (let row = currentRow + 1; row * (itemWidth + spacing) < container.width; row++) {
        let y = row * (itemWidth + spacing);
        
        // ✅ ตรวจสอบความกว้างตู้
        if (y + itemWidth > container.width) {
            continue;
        }
        
        // ✅ หาตำแหน่งเริ่มต้นในแถวนี้
        for (let x = 0; x <= container.length - itemLength; x++) {
            // ✅ ตรวจสอบพื้นที่ว่าง
            if (isAreaAvailable(heightMap, x, y, rotation, currentHeight, spacing, container)) {
                return {
                    x: x,
                    y: y,
                    z: currentHeight,
                    row: row,
                    column: x + itemLength + spacing,
                    height: currentHeight
                };
            }
        }
    }
    
    return null;
}

// ✅ ตรวจสอบพื้นที่ว่าง
function isAreaAvailable(heightMap, startX, startY, rotation, height, spacing, container) {
    // ✅ ใช้การตรวจสอบแบบพื้นฐาน (ไม่ตรวจสอบฐาน) สำหรับฟังก์ชันอื่นๆ ที่ยังใช้อยู่
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // ตรวจสอบพื้นที่หลัก
    for (let x = startX; x < startX + itemLength; x++) {
        for (let y = startY; y < startY + itemWidth; y++) {
            if (x >= container.length || y >= container.width) {
                return false;
            }
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (!heightMap[x] || heightMap[x][y] === undefined) { 
                return false; 
            }
            // heightMap ใน simple algorithm เก็บแค่ตัวเลขความสูง
            if (heightMap[x][y] > height) { 
                return false;
            }
        }
    }
    
    // ตรวจสอบพื้นที่ระยะห่าง
    const spacingEndX = Math.min(container.length, startX + itemLength + spacing);
    const spacingEndY = Math.min(container.width, startY + itemWidth + spacing);
    
    for (let x = startX; x < spacingEndX; x++) {
        for (let y = startY; y < spacingEndY; y++) {
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < container.length && y < container.width && heightMap[x] && heightMap[x][y] !== undefined && heightMap[x][y] > height) {
                return false;
            }
        }
    }
    
    return true;
}

// ✅ อัพเดท heightMap 3D
function update3DHeightMap(heightMap, position, item, rotation, spacing, container) {
    const newHeight = position.z + item.height;
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // ✅ อัพเดทพื้นที่หลัก
    for (let x = position.x; x < position.x + itemLength; x++) {
        for (let y = position.y; y < position.y + itemWidth; y++) {
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < container.length && y < container.width && heightMap[x]) {
                // heightMap ใน simple algorithm เก็บแค่ตัวเลขความสูง
                heightMap[x][y] = newHeight;
            }
        }
    }
    
    // ✅ อัพเดทพื้นที่ระยะห่าง
    const spacingEndX = Math.min(container.length, position.x + itemLength + spacing);
    const spacingEndY = Math.min(container.width, position.y + itemWidth + spacing);
    
    for (let x = position.x; x < spacingEndX; x++) {
        for (let y = position.y; y < spacingEndY; y++) {
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < container.length && y < container.width && heightMap[x]) {
                heightMap[x][y] = Math.max(heightMap[x][y], newHeight);
            }
        }
    }
}

function advancedLoadingAlgorithm(items, container, conditions = {}) {
    console.log('🚀 เริ่มคำนวณการจัดเรียงแบบซ้อนได้');
    
    let plan = [];
    let totalWeight = 0;
    let totalVolume = 0;
    
    const sortedItems = sortItemsByBaseArea(items, conditions);
    const spacing = conditions.itemSpacing || 0;
    
    // ✅ สร้าง 3D grid สำหรับติดตามตำแหน่ง
    const grid = initialize3DGrid(container);
    
    for (let item of sortedItems) {
        if (totalWeight + item.weight > container.maxWeight) {
            console.log(`⚠️ ข้ามสินค้า ${item.name} เนื่องจากน้ำหนักเกิน`);
            continue;
        }

        let placed = false;
        const rotations = generateAllRotations(item, conditions, container);
        
        // ✅ หาตำแหน่งที่ดีที่สุด (รวมการซ้อน)
        const bestPosition = findBest3DPosition(item, rotations, grid, container, conditions);
        
        if (bestPosition) {
            const packedItem = {
                ...item,
                position: { 
                    x: bestPosition.x, 
                    y: bestPosition.y, 
                    z: bestPosition.z
                },
                actualSize: {
                    length: bestPosition.rotation.length,
                    width: bestPosition.rotation.width, 
                    height: bestPosition.rotation.height
                },
                color: item.color || getColorByItemType(item),
                rotationType: bestPosition.rotation.type,
                baseArea: bestPosition.rotation.length * bestPosition.rotation.width,
                // ✅ บันทึกข้อมูลชั้นเบื้องต้น
                initialLayer: Math.floor(bestPosition.z / bestPosition.rotation.height) + 1
            };

            plan.push(packedItem);
            totalWeight += item.weight;
            totalVolume += (packedItem.actualSize.length * packedItem.actualSize.width * packedItem.actualSize.height);
            
            // ✅ อัพเดท grid
            update3DGrid(grid, bestPosition, item, container, conditions);
            placed = true;
            
            console.log(`✅ วางสินค้า ${item.name} ที่:`, {
                ตำแหน่ง: `${bestPosition.x}, ${bestPosition.y}, ${bestPosition.z}`,
                ขนาด: `${bestPosition.rotation.length}x${bestPosition.rotation.width}x${bestPosition.rotation.height}`,
                ชั้นเบื้องต้น: packedItem.initialLayer
            });
        }
        
        if (!placed) {
            console.log(`❌ ไม่สามารถวางสินค้า ${item.name} ได้`);
        }
    }

    // ✅ คำนวณจำนวนชั้นที่ถูกต้องหลังจากวางสินค้าทั้งหมด
    const actualLayersUsed = calculateActualLayersUsed(plan);
    
    const containerVolume = container.length * container.width * container.height;
    const volumeEfficiency = (totalVolume / containerVolume) * 100;
    const weightEfficiency = (totalWeight / container.maxWeight) * 100;

    console.log('📊 สรุปการจัดเรียงแบบซ้อน:', {
        สินค้าที่จัดได้: plan.length,
        จำนวนชั้นที่คำนวณ: actualLayersUsed,
        ประสิทธิภาพปริมาตร: `${volumeEfficiency.toFixed(1)}%`,
        ประสิทธิภาพน้ำหนัก: `${weightEfficiency.toFixed(1)}%`
    });

    return {
        plan: plan,
        totalWeight: totalWeight,
        totalVolume: totalVolume,
        layersUsed: actualLayersUsed, // ✅ ส่งจำนวนชั้นที่ถูกต้อง
        efficiency: {
            volume: Math.round(volumeEfficiency),
            weight: Math.round(weightEfficiency),
            overall: Math.round((volumeEfficiency * 0.7 + weightEfficiency * 0.3))
        },
        conditions: conditions
    };
}

// ✅ เพิ่มฟังก์ชันคำนวณจำนวนชั้นจริง
function calculateActualLayersUsed(plan) {
    if (!plan || plan.length === 0) return 0;
    
    const layers = new Set();
    
    // ✅ ใช้ฟังก์ชันคำนวณชั้นที่ถูกต้องจาก app.js (สมมติว่าอยู่ใน Global Scope)
    if (typeof calculateExactLayer === 'function') {
        plan.forEach(item => {
            const layer = calculateExactLayer(item, plan);
            layers.add(layer);
        });
    } else {
        // Fallback: นับจาก Z position
        plan.forEach(item => {
            const layer = Math.floor(item.position.z / (item.actualSize?.height || item.height)) + 1;
            layers.add(layer);
        });
    }
    
    return layers.size;
}

// ✅ สร้างการหมุนทั้งหมด (รวมความสูง)
function generateAllRotations(item, conditions, container) {
    const rotations = [];
    const dimensions = [
        { length: item.length, width: item.width, height: item.height, type: 'original' },
        { length: item.length, width: item.height, height: item.width, type: 'rotate_yz' },
        { length: item.width, width: item.length, height: item.height, type: 'rotate_xy' },
        { length: item.width, width: item.height, height: item.length, type: 'rotate_xyz' },
        { length: item.height, width: item.length, height: item.width, type: 'rotate_xz' },
        { length: item.height, width: item.width, height: item.length, type: 'rotate_zyx' }
    ];
    
    for (let dim of dimensions) {
        // ตรวจสอบว่าขนาดพอดีกับตู้
        if (dim.length <= container.length && 
            dim.width <= container.width && 
            dim.height <= container.height) {
            rotations.push(dim);
        }
    }
    
    // ถ้าไม่อนุญาตการหมุน ให้ใช้เฉพาะการวางแนวเดิม
    if (!conditions.allowRotation && rotations.length > 0) {
        return [rotations[0]];
    }
    
    return rotations;
}

// ✅ สร้าง 3D grid
function initialize3DGrid(container) {
    console.log(`📐 สร้าง 3D grid: ${container.length}x${container.width}`);
    
    const grid = [];
    for (let x = 0; x < container.length; x++) {
        grid[x] = [];
        for (let y = 0; y < container.width; y++) {
            grid[x][y] = 0; // ความสูงปัจจุบันที่ตำแหน่ง (x,y)
        }
    }
    return grid;
}

// ✅ หาตำแหน่ง 3D ที่ดีที่สุด
function findBest3DPosition(item, rotations, grid, container, conditions) {
    let bestPosition = null;
    let bestScore = -Infinity;
    const spacing = conditions.itemSpacing || 0;

    for (let rotation of rotations) {
        for (let x = 0; x <= container.length - rotation.length; x++) {
            for (let y = 0; y <= container.width - rotation.width; y++) {
                // ✅ หาความสูงที่สามารถวางได้
                const baseHeight = getBaseHeight(grid, x, y, rotation.length, rotation.width);
                
                // ✅ ตรวจสอบความสูงตู้
                if (baseHeight + rotation.height <= container.height) {
                    // ✅ ตรวจสอบพื้นที่ว่าง (รวมระยะห่าง)
                    if (is3DAreaAvailable(grid, x, y, baseHeight, rotation, spacing, container)) {
                        // ✅ คำนวณคะแนน (ให้ความสำคัญกับการวางต่ำก่อน)
                        const score = calculatePositionScore(x, y, baseHeight, rotation, grid, container);
                        
                        if (score > bestScore) {
                            bestScore = score;
                            bestPosition = {
                                x: x,
                                y: y,
                                z: baseHeight,
                                rotation: rotation
                            };
                        }
                    }
                }
            }
        }
    }
    
    return bestPosition;
}

// ✅ หาความสูงฐานที่ตำแหน่งนั้น
function getBaseHeight(grid, startX, startY, length, width) {
    let maxHeight = 0;
    for (let x = startX; x < startX + length; x++) {
        for (let y = startY; y < startY + width; y++) {
            // FIX: เพิ่มการตรวจสอบขอบเขตและตรวจสอบว่า grid[x] มีอยู่จริง
            if (x < grid.length && grid[x] && y < grid[x].length) { 
                maxHeight = Math.max(maxHeight, grid[x][y]);
            }
        }
    }
    return maxHeight;
}

// ✅ ตรวจสอบพื้นที่ 3D
function is3DAreaAvailable(grid, startX, startY, height, rotation, spacing, container) {
    const endX = Math.min(container.length, startX + rotation.length + spacing);
    const endY = Math.min(container.width, startY + rotation.width + spacing);
    
    // ตรวจสอบพื้นที่หลักและพื้นที่ระยะห่าง
    for (let x = startX; x < endX; x++) {
        for (let y = startY; y < endY; y++) {
             // FIX: เพิ่มการตรวจสอบว่า grid[x] มีอยู่จริง
            if (x < grid.length && grid[x] && y < grid[x].length) {
                if (grid[x][y] > height) {
                    return false;
                }
            } else {
                 if (x < container.length && y < container.width) {
                     return false; 
                 }
            }
        }
    }
    
    return true;
}

// ✅ [แก้ไข] ฟังก์ชันคำนวณคะแนนตำแหน่ง - เน้นการวางด้านใน (Z-axis, Y-axis) ก่อน
function calculatePositionScore(x, y, z, rotation, grid, container) {
    let score = 0;
    
    // 1. ให้คะแนนการวางต่ำ (z น้อย) - เน้นการซ้อนก่อน (คะแนนสูง)
    // การวางที่ Z=0 จะได้คะแนนสูงสุดสำหรับการซ้อน
    score += (container.height - z) * 20; // เพิ่มน้ำหนักให้กับการวางต่ำ
    
    // 2. ให้คะแนนการวางชิดด้านใน/ด้านหลัง (Y-axis น้อย) - เน้น Back-to-Front
    // การวางที่ Y=0 จะได้คะแนนสูงสุด
    score += (container.width - y - rotation.width) * 5; 

    // 3. ให้คะแนนการวางชิดด้านใน/ด้านหน้าตู้ (X-axis น้อย)
    // การวางที่ X=0 จะได้คะแนนสูงสุด
    score += (container.length - x - rotation.length) * 0.1;
    
    // 4. ให้คะแนนการวางบนสินค้าอื่น (สร้างเสถียรภาพ)
    if (z > 0) {
        score += 5;
    }
    
    return score;
}

// ✅ อัพเดท 3D grid
function update3DGrid(grid, position, item, container, conditions) {
    const newHeight = position.z + position.rotation.height;
    const spacing = conditions.itemSpacing || 0;
    
    const endX = Math.min(container.length, position.x + position.rotation.length + spacing);
    const endY = Math.min(container.width, position.y + position.rotation.width + spacing);
    
    for (let x = position.x; x < endX; x++) {
        for (let y = position.y; y < endY; y++) {
             // FIX: เพิ่มการตรวจสอบว่า grid[x] มีอยู่จริง
            if (x < grid.length && grid[x] && y < grid[x].length) {
                grid[x][y] = Math.max(grid[x][y], newHeight);
            }
        }
    }
}

// ✅ ฟังก์ชันคำนวณความสูงที่ตำแหน่งนั้น (ปรับปรุง)
function getStackHeightAtPosition(heightMap, startX, startY, length, width) {
    let maxHeight = 0;
    for (let x = startX; x < startX + length; x++) {
        for (let y = startY; y < startY + width; y++) {
             // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < heightMap.length && heightMap[x] && y < heightMap[x].length) {
                maxHeight = Math.max(maxHeight, heightMap[x][y]);
            }
        }
    }
    return maxHeight;
}

// ✅ [แก้ไข] ฟังก์ชันหาตำแหน่งสำหรับผนัง
function findWallPosition(item, rotation, heightMap, container, wallSide, wallX, currentRow, currentHeight, spacing, conditions) {
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    let x, y, nextX;
    
    // ✅ ตำแหน่ง Y (แถว)
    y = currentRow * (itemWidth + spacing);
    
    if (wallSide === 'left') {
        // ผนังซ้าย: เริ่มจาก X ปัจจุบันไปทางขวา
        x = wallX;
        nextX = x + itemLength + spacing;
    } else {
        // ผนังขวา: เริ่มจาก X ปัจจุบันมาทางซ้าย
        x = wallX - itemLength;
        nextX = x - spacing;
    }
    
    // ✅ ตรวจสอบขอบเขต
    if (y + itemWidth > container.width) {
        return null; // ไม่พอดีกับความกว้าง
    }
    
    if (x < 0 || x + itemLength > container.length) {
        // ต้องไม่วางสินค้าออกนอกตู้
        return null; 
    }
    
    // ✅ ตรวจสอบพื้นที่ว่าง
    if (!isAreaAvailable(heightMap, x, y, rotation, currentHeight, spacing, container)) {
        return null;
    }
    
    return {
        x: x,
        y: y,
        z: currentHeight,
        row: currentRow,
        column: nextX, // column คือตำแหน่ง X ใหม่สำหรับสินค้าชิ้นต่อไป
        height: currentHeight,
        wallSide: wallSide
    };
}

// ✅ ฟังก์ชันสร้างสินค้า
function createPackedItem(item, position, baseSize) {
    return {
        ...item,
        position: { 
            x: position.x, 
            y: position.y, 
            z: position.z
        },
        actualSize: {
            length: item.length,
            width: item.width, 
            height: item.height
        },
        color: getColorForBaseGroup(baseSize),
        rotationType: 'original',
        baseArea: item.length * item.width,
        baseGroup: baseSize,
        stackedOn: position.stackedOn || null,
        stackLevel: position.stackLevel || 0
    };
}

// calculations.js - แก้ไขฟังก์ชัน sameBaseGroupLoadingAlgorithm
function sameBaseGroupLoadingAlgorithm(items, container, conditions = {}) {
    console.log('🚀 เริ่มคำนวณการจัดเรียงแบบกลุ่มฐานเดียวกัน (เน้นซ้อนจากด้านใน)');
    
    let plan = [];
    let totalWeight = 0;
    let totalBaseArea = 0;
    
    const spacing = conditions.itemSpacing || 0;
    
    // ✅ [แก้ไข] ใช้ heightMap ที่เก็บข้อมูลฐาน
    const heightMap = initialize3DHeightMapWithBase(container); 
    
    // ✅ จัดกลุ่มและเรียงตามพื้นที่ฐานจากมากไปน้อย
    const baseGroups = groupItemsByBaseSize(items);
    const sortedGroups = sortBaseGroupsByArea(baseGroups);
    
    console.log('📊 กลุ่มสินค้าตามขนาดฐาน:', sortedGroups.map(g => ({
        baseSize: g.baseSize,
        count: g.items.length
    })));

    // ✅ จัดเรียงทีละกลุ่ม (จากฐานใหญ่ไปฐานเล็ก)
    for (let group of sortedGroups) {
        console.log(`\n📦 กำลังจัดเรียงกลุ่มฐาน ${group.baseSize}: ${group.items.length} ชิ้น`);
        
        let groupPlacedCount = 0;
        
        for (let item of group.items) {
            if (totalWeight + item.weight > container.maxWeight) {
                console.log(`⚠️ ข้ามสินค้า ${item.name} เนื่องจากน้ำหนักเกิน`);
                continue;
            }
            
            let placed = false;
            
            const rotation = { 
                baseLength: item.length, 
                baseWidth: item.width, 
                rotation: 'original' 
            };
            
            // ✅ 1. (PRIORITY 1) ลองหาตำแหน่งซ้อนบนสินค้าในกลุ่มเดียวกันก่อน (ใช้ Max Height)
            const alreadyPlacedItemsInThisGroup = plan.filter(p => p.baseGroup === group.baseSize);
            let position = findStrictStackPosition(item, group.baseSize, alreadyPlacedItemsInThisGroup, heightMap, container, spacing, conditions);

            // ✅ 2. (PRIORITY 2) ถ้าซ้อนไม่ได้ ให้ลองวางบนพื้น (เน้น Back-to-Front)
            if (!position) {
                position = findGroundPositionForStrictStacking(item, group.baseSize, heightMap, container, spacing, conditions);
            }
            
            // ✅ 3. (PRIORITY 3) ลองตำแหน่งใดๆ ในตู้ (สำรอง)
            if (!position) {
                position = findAnyPositionForStrictStacking(item, group.baseSize, heightMap, container, spacing, conditions);
            }
            
            if (position) {
                const packedItem = createPackedItemForGroup(item, position, group.baseSize);
                plan.push(packedItem);
                totalWeight += item.weight;
                totalBaseArea += packedItem.baseArea;
                
                // ✅ อัพเดท heightMap พร้อมข้อมูลฐาน
                update3DHeightMapWithBase(heightMap, position, item, rotation, group.baseSize, spacing, container);
                
                placed = true;
                groupPlacedCount++;
                
                console.log(`✅ วาง ${item.name} ที่ (${position.x}, ${position.y}, ${position.z}) ซ้อนบน: ${position.stackedOn || 'พื้น'}`);
            }
            
            if (!placed) {
                console.log(`❌ ไม่สามารถวางสินค้า ${item.name} ได้`);
            }
        }
        
        console.log(`📊 กลุ่ม ${group.baseSize}: วางได้ ${groupPlacedCount}/${group.items.length} ชิ้น`);
    }
    
    // ... (rest of the function: validation and summary)
    
    const containerBaseArea = container.length * container.width;
    const areaEfficiency = (totalBaseArea / containerBaseArea) * 100;
    const weightEfficiency = (totalWeight / container.maxWeight) * 100;

    return {
        plan: plan,
        totalWeight: totalWeight,
        totalBaseArea: totalBaseArea,
        efficiency: {
            area: Math.round(areaEfficiency),
            weight: Math.round(weightEfficiency),
            overall: Math.round((areaEfficiency * 0.7 + weightEfficiency * 0.3))
        },
        conditions: conditions,
        baseGroups: sortedGroups
    };
}

// ✅ ฟังก์ชันหาสินค้าที่อยู่ด้านล่าง
function findItemsBelow(item, plan) {
    const belowItems = [];
    const itemLength = item.actualSize?.length || item.length;
    const itemWidth = item.actualSize?.width || item.width;
    
    for (let otherItem of plan) {
        if (otherItem.id === item.id) continue;
        
        const otherLength = otherItem.actualSize?.length || otherItem.length;
        const otherWidth = otherItem.actualSize?.width || otherItem.width;
        const otherHeight = otherItem.actualSize?.height || otherItem.height;
        
        // ตรวจสอบว่าอยู่ด้านล่างและสัมผัสกัน
        const isBelowX = otherItem.position.x < item.position.x + itemLength && 
                        otherItem.position.x + otherLength > item.position.x;
        const isBelowY = otherItem.position.y < item.position.y + itemWidth && 
                        otherItem.position.y + otherWidth > item.position.y;
        const isBelowZ = Math.abs(otherItem.position.z + otherHeight - item.position.z) < 1; // เกือบสัมผัสกัน
        
        if (isBelowX && isBelowY && isBelowZ) {
            belowItems.push(otherItem);
        }
    }
    
    return belowItems;
}

// ✅ ฟังก์ชันตรวจสอบว่าสามารถวางได้ที่ตำแหน่งนี้
function canPlaceAtPosition(grid, startX, startY, length, width, height, baseSize, container) {
    // ✅ ตรวจสอบทุกเซลล์ในพื้นที่ที่จะวาง
    for (let x = startX; x < startX + length; x++) {
        for (let y = startY; y < startY + width; y++) {
             // FIX: เพิ่มการตรวจสอบว่า grid[x] มีอยู่จริง
            if (x >= container.length || y >= container.width || !grid[x]) {
                return false; // ออกนอกขอบเขต
            }
            
            // FIX: เพิ่มการตรวจสอบว่า grid[x][y] มีอยู่จริง
            if (!grid[x][y]) {
                return false;
            }
            
            const cell = grid[x][y];
            
            // ✅ ตรวจสอบว่ามีสินค้าอื่นอยู่ที่ตำแหน่งนี้หรือไม่ (แม้จะอยู่บนพื้น)
            if (cell.baseSize !== null && cell.baseSize !== baseSize) {
                return false; // มีสินค้าฐานอื่นอยู่แล้ว
            }
            
            // ✅ ตรวจสอบความสูง (สำหรับการซ้อน)
            if (cell.height > height) {
                return false; // มีสินค้าอื่นอยู่สูงกว่า
            }
        }
    }
    return true;
}

// ✅ ฟังก์ชันหาตำแหน่งใดๆ ในตู้แบบ Strict (ให้ X, Y เริ่มจาก 0 เพื่อเน้นด้านใน)
function findAnyPositionForStrictStacking(item, baseSize, heightMap, container, spacing, conditions) {
    const itemLength = item.length;
    const itemWidth = item.width;
    
    // ✅ ค้นหาทุกตำแหน่งในตู้ (ตรวจสอบฐานอย่างเคร่งครัด)
    for (let y = 0; y <= container.width - itemWidth; y++) {
        for (let x = 0; x <= container.length - itemLength; x++) {
            
            // 1. ตรวจสอบพื้นที่ว่างบนความสูงปัจจุบัน
            const stackInfo = getStackHeightWithBase(heightMap, x, y, itemLength, itemWidth, baseSize, spacing, container);
            const height = stackInfo.height;
            
            if (height + item.height <= container.height) {
                if (isAreaAvailableForStrictStacking(heightMap, x, y, {
                    baseLength: itemLength,
                    baseWidth: itemWidth
                }, height, baseSize, spacing, container)) {
                    
                    const row = Math.floor(y / (itemWidth + spacing));
                    return {
                        x: x,
                        y: y, 
                        z: Math.round(height * 1000) / 1000, // 👈 ปัดเศษ Z-position 3 ตำแหน่ง
                        row: row,
                        column: x + itemLength + spacing,
                        height: height,
                        baseGroup: baseSize,
                        stackedOn: stackInfo.stackedOn || null,
                        stackLevel: stackInfo.stackLevel || 0
                    };
                }
            }
        }
    }
    
    return null;
}

// ✅ ฟังก์ชันคำนวณความสูงที่ตำแหน่งนั้น (ตรวจสอบฐาน) - [แก้ไขเพื่อรวม Spacing ในการตรวจสอบความสูง]
function getStackHeightWithBase(heightMap, startX, startY, length, width, baseSize, spacing, container) {
    let maxHeight = 0;
    let stackedOn = null;
    let stackLevel = 0;
    
    // **[สำคัญ]** กำหนดขอบเขตการตรวจสอบที่รวม Spacing เพื่อให้แน่ใจว่าวางสินค้าบนพื้นผิวที่สูงที่สุด (ป้องกันการลอย)
    const endX = Math.min(container.length, startX + length + spacing);
    const endY = Math.min(container.width, startY + width + spacing);

    for (let x = startX; x < endX; x++) {
        for (let y = startY; y < endY; y++) {
            
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < heightMap.length && heightMap[x] && y < heightMap[x].length) {
                const cell = heightMap[x][y];
                
                if (!cell) continue; 
                
                // 1. **[สำคัญ]** หาความสูงสูงสุดที่ถูกครอบครองจริง ๆ (ป้องกันการลอย)
                maxHeight = Math.max(maxHeight, cell.height); 
                
                // 2. เก็บข้อมูลของสินค้าที่ทำให้เกิดความสูงสูงสุด (สำหรับซ้อนบนฐานเดียวกัน)
                //    ตรวจสอบเฉพาะในพื้นที่ฐานหลักเท่านั้น (ไม่รวมพื้นที่ Spacing)
                if (x < startX + length && y < startY + width) { 
                     if (cell.itemId && cell.baseSize === baseSize) {
                        if (cell.height >= maxHeight) {
                            stackedOn = cell.itemId;
                            stackLevel = Math.max(stackLevel, (cell.stackLevel || 0) + 1);
                        }
                    }
                }
            }
        }
    }
    
    // **[สำคัญ]** ปัดเศษความสูงที่พบ
    const roundedMaxHeight = Math.round(maxHeight * 1000) / 1000; // 👈 ปัดเศษ 3 ตำแหน่ง

    return {
        height: roundedMaxHeight, // 👈 ใช้ค่าที่ปัดเศษ
        stackedOn: stackedOn,
        stackLevel: stackLevel
    };
}

function update3DHeightMapWithBase(heightMap, position, item, rotation, baseSize, spacing, container) {
    // **[สำคัญ]** ปัดเศษความสูงที่วาง
    const newHeight = Math.round((position.z + item.height) * 1000) / 1000; // 👈 ปัดเศษ 3 ตำแหน่ง
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // ✅ อัพเดทพื้นที่หลัก
    for (let x = position.x; x < position.x + itemLength; x++) {
        for (let y = position.y; y < position.y + itemWidth; y++) {
             // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < container.length && heightMap[x] && y < container.width) {
                heightMap[x][y] = {
                    height: newHeight, // 👈 ใช้ค่าที่ปัดเศษแล้ว
                    baseSize: baseSize,
                    itemId: item.id,
                    stackLevel: position.stackLevel || 0
                };
            }
        }
    }
    
    // ✅ อัพเดทพื้นที่ระยะห่าง
    const spacingEndX = Math.min(container.length, position.x + itemLength + spacing);
    const spacingEndY = Math.min(container.width, position.y + itemWidth + spacing);
    
    for (let x = position.x; x < spacingEndX; x++) {
        for (let y = position.y; y < spacingEndY; y++) { 
             // FIX: เพิ่มการตรวจสอบว่า heightMap[x] มีอยู่จริง
            if (x < container.length && heightMap[x] && y < container.width) {
                // ✅ สำหรับพื้นที่ระยะห่าง ให้อัพเดทเฉพาะความสูง (ไม่บันทึกข้อมูลฐาน)
                if (heightMap[x][y].height < newHeight) {
                    heightMap[x][y].height = newHeight; // 👈 ใช้ค่าที่ปัดเศษแล้ว
                }
            }
        }
    }
}

// ✅ ฟังก์ชันหาตำแหน่งซ้อนกันแบบ Strict (เฉพาะฐานเดียวกัน)
function findStrictStackPosition(item, baseSize, groupPlan, heightMap, container, spacing, conditions) {
    const itemLength = item.length;
    const itemWidth = item.width;
    
    // ✅ หาจากสินค้าในกลุ่มเดียวกันที่วางแล้วเท่านั้น
    for (let placedItem of groupPlan) {
        // ตรวจสอบว่าเป็นสินค้าในกลุ่มเดียวกัน (ฐานต้องตรงกัน)
        const placedLength = placedItem.actualSize?.length || placedItem.length;
        const placedWidth = placedItem.actualSize?.width || placedItem.width;
        
        // ✅ ตรวจสอบว่าฐานตรงกัน (เป็นสินค้าในกลุ่มเดียวกัน)
        if (itemLength === placedLength && itemWidth === placedWidth) {
            const stackX = placedItem.position.x;
            const stackY = placedItem.position.y;
            
            // --- MODIFICATION: ใช้ getStackHeightWithBase ที่แก้ไขแล้ว ---
            const stackInfo = getStackHeightWithBase(heightMap, stackX, stackY, itemLength, itemWidth, baseSize, spacing, container);
            const stackZ = stackInfo.height;
            // --------------------------------------------------------------------
            
            // ตรวจสอบความสูงตู้
            if (stackZ + item.height > container.height) {
                continue;
            }
            
            // ✅ ตรวจสอบพื้นที่ว่างและตรวจสอบว่าสามารถซ้อนได้ (ฐานต้องตรงกัน)
            if (isAreaAvailableForStrictStacking(heightMap, stackX, stackY, {
                baseLength: itemLength,
                baseWidth: itemWidth
            }, stackZ, baseSize, spacing, container)) {
                
                const row = Math.floor(stackY / (itemWidth + spacing));
                return {
                    x: stackX,
                    y: stackY,
                    z: Math.round(stackZ * 1000) / 1000, // 👈 ปัดเศษ Z-position 3 ตำแหน่ง
                    row: row,
                    column: stackX + itemLength + spacing,
                    height: stackZ,
                    baseGroup: baseSize,
                    stackedOn: stackInfo.stackedOn, // ใช้ชื่อสินค้าที่ทำให้เกิดความสูงสูงสุด
                    stackLevel: stackInfo.stackLevel // ใช้ StackLevel ที่คำนวณจาก MaxHeight
                };
            }
        }
    }
    
    return null;
}

// ✅ [ปรับปรุง] ฟังก์ชันหาตำแหน่งบนพื้นแบบ Strict (ให้ X, Y เริ่มจาก 0 เพื่อเน้นด้านใน)
function findGroundPositionForStrictStacking(item, baseSize, heightMap, container, spacing, conditions) {
    const itemLength = item.length;
    const itemWidth = item.width;
    
    // ✅ ค้นหาตำแหน่งว่างบนพื้น (ความสูง = 0)
    // 💡 ปรับการวนลูป: วน Y (ความกว้าง/ความลึก) ก่อน X (ความยาว) เพื่อให้เน้น Back-to-Front
    for (let y = 0; y <= container.width - itemWidth; y += (itemWidth + spacing)) {
        for (let x = 0; x <= container.length - itemLength; x++) {
            
            // 1. ตรวจสอบพื้นที่ว่างบนความสูงปัจจุบัน (Z=0)
            const stackInfo = getStackHeightWithBase(heightMap, x, y, itemLength, itemWidth, baseSize, spacing, container);
            const height = stackInfo.height;
            
            if (height === 0 && item.height <= container.height) { // ตรวจสอบว่าวางบนพื้นและไม่เกินความสูงตู้
                if (isAreaAvailableForStrictStacking(heightMap, x, y, {
                    baseLength: itemLength,
                    baseWidth: itemWidth
                }, height, baseSize, spacing, container)) {
                    
                    const row = Math.floor(y / (itemWidth + spacing));
                    return {
                        x: x,
                        y: y, // 👈 ตำแหน่ง Y ต่ำสุดก่อน (ด้านในตู้)
                        z: Math.round(height * 1000) / 1000, // 👈 ปัดเศษ Z-position 3 ตำแหน่ง
                        row: row,
                        column: x + itemLength + spacing,
                        height: height,
                        baseGroup: baseSize,
                        stackedOn: null,
                        stackLevel: 0
                    };
                }
            }
        }
    }
    
    return null;
}

// ✅ ฟังก์ชันตรวจสอบพื้นที่ว่างแบบ Partial Stacking (ฐานเท่ากันหรือฐานเก่าใหญ่กว่า)
function isAreaAvailableForStrictStacking(heightMap, startX, startY, rotation, height, baseSize, spacing, container) {
    const itemLength = rotation.baseLength;
    const itemWidth = rotation.baseWidth;
    
    // แปลง baseSize เป็นตัวเลข
    const [newWidth, newLength] = baseSize.split('x').map(Number);
    const newArea = newWidth * newLength; 
    
    // ✅ ตรวจสอบพื้นที่หลัก
    for (let x = startX; x < startX + itemLength; x++) {
        for (let y = startY; y < startY + itemWidth; y++) {
            if (x >= container.length || y >= container.width) {
                return false; // ออกนอกขอบเขต
            }
            
            // FIX: เพิ่มการตรวจสอบว่า heightMap[x] และ heightMap[x][y] มีอยู่จริง
            if (!heightMap[x] || !heightMap[x][y]) {
                console.warn(`[StrictStacking] Cell undefined at [${x}][${y}]. Returning false defensively.`);
                return false; 
            }
            
            const cell = heightMap[x][y];
            
            // 1. ตรวจสอบ Cross-Base Clash: ห้ามวางทับ/ติดกับฐานอื่น
            if (cell.baseSize !== null) {
                 const [cellWidth, cellLength] = cell.baseSize.split('x').map(Number);
                 const cellArea = cellWidth * cellLength;
                 
                 // **[เงื่อนไขใหม่]** อนุญาตให้วางถ้า: 
                 //   a) ฐานเท่ากัน OR 
                 //   b) ฐานใหม่ (newArea) เล็กกว่าฐานเก่า (cellArea)
                 if (newArea > cellArea) { 
                     return false; // ห้ามวางฐานใหญ่กว่าบนฐานที่เล็กกว่า
                 }
            }
            
            // 2. ตรวจสอบความสูง: ห้ามวางเข้าไปในสินค้าที่สูงกว่า (Physical Clash)
            if (cell.height > height) { 
                return false; // ห้ามวางเข้าไปในสินค้าที่สูงกว่า
            }
        }
    }
    
    // ✅ ตรวจสอบพื้นที่ระยะห่าง (ด้านขวาและล่าง)
    const spacingEndX = Math.min(container.length, startX + itemLength + spacing);
    const spacingEndY = Math.min(container.width, startY + itemWidth + spacing);
    
    for (let x = startX; x < spacingEndX; x++) {
        for (let y = startY; y < spacingEndY; y++) {
            if (x < container.length && y < container.width) {
                
                // FIX: เพิ่มการตรวจสอบว่า heightMap[x] และ heightMap[x][y] มีอยู่จริง (แก้ปัญหา TypeError)
                if (!heightMap[x] || !heightMap[x][y]) {
                     console.warn(`[StrictStacking] Spacing cell undefined at [${x}][${y}]. Returning false defensively.`);
                     return false; 
                }
                
                const cell = heightMap[x][y];
                if (cell.height > height) {
                    return false;
                }
                // ✅ ตรวจสอบฐานสำหรับพื้นที่ระยะห่าง
                if (cell.baseSize !== null) {
                    const [cellWidth, cellLength] = cell.baseSize.split('x').map(Number);
                    const cellArea = cellWidth * cellLength;
                    
                    if (newArea > cellArea) {
                        return false; // ห้ามวางฐานใหญ่กว่าบนฐานที่เล็กกว่า (แม้แต่ในพื้นที่ Spacing)
                    }
                }
            }
        }
    }
    
    return true;
}

// ✅ ฟังก์ชันจัดกลุ่มสินค้า (จำเป็นต้องอยู่ใน calculations.js)
function groupItemsByBaseSize(items) {
    const baseGroups = new Map();
    
    items.forEach(item => {
        // ใช้ขนาดจริงของสินค้าในการจัดกลุ่ม
        const baseKey = `${Math.min(item.length, item.width)}x${Math.max(item.length, item.width)}`;
        
        if (!baseGroups.has(baseKey)) {
            baseGroups.set(baseKey, {
                baseSize: baseKey,
                baseLength: Math.min(item.length, item.width),
                baseWidth: Math.max(item.length, item.width),
                items: []
            });
        }
        
        baseGroups.get(baseKey).items.push(item);
    });
    
    return Array.from(baseGroups.values());
}

// ✅ ฟังก์ชันเรียงลำดับกลุ่ม (จำเป็นต้องอยู่ใน calculations.js)
function sortBaseGroupsByArea(groups) {
    return groups.sort((a, b) => {
        const areaA = a.baseLength * a.baseWidth;
        const areaB = b.baseLength * b.baseWidth;
        return areaB - areaA;
    });
}

// ✅ ฟังก์ชันสร้าง heightMap ที่บันทึกข้อมูลฐาน (จำเป็นต้องอยู่ใน calculations.js)
function initialize3DHeightMapWithBase(container) {
    console.log(`📐 สร้าง 3D heightMap พร้อมข้อมูลฐาน: ${container.length}x${container.width}`);
    
    const heightMap = [];
    for (let x = 0; x < container.length; x++) {
        heightMap[x] = [];
        for (let y = 0; y < container.width; y++) {
            heightMap[x][y] = {
                height: 0,           // ความสูงปัจจุบัน
                baseSize: null,      // ขนาดฐานของสินค้าที่อยู่บนสุด
                itemId: null         // ID ของสินค้าที่อยู่บนสุด
            };
        }
    }
    return heightMap;
}

// ✅ ฟังก์ชันสร้างสินค้าที่จัดเรียงแล้วสำหรับกลุ่ม
function createPackedItemForGroup(item, position, baseSize) {
    return {
        ...item,
        position: { 
            x: position.x, 
            y: position.y, 
            z: position.z
        },
        actualSize: {
            length: item.length,
            width: item.width, 
            height: item.height
        },
        color: getColorForBaseGroup(baseSize),
        rotationType: 'original',
        baseArea: item.length * item.width,
        baseGroup: baseSize,
        stackedOn: position.stackedOn || null,
        stackLevel: position.stackLevel || 0
    };
}